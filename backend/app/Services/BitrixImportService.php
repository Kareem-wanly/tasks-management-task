<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Bitrix\SyncBitrixChecklistAction;
use App\Actions\Bitrix\SyncBitrixProjectAction;
use App\Actions\Bitrix\SyncBitrixTaskAction;
use App\Actions\Bitrix\SyncBitrixTimeLogAction;
use App\Actions\Bitrix\SyncBitrixUserAction;
use App\Enums\BitrixSyncOperation;
use App\Enums\QueueName;
use App\Enums\TaskMemberRole;
use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\BitrixPayload;
use App\Integrations\Bitrix24\Contracts\BitrixChecklistRepositoryInterface;
use App\Integrations\Bitrix24\Contracts\BitrixDeletionGuardInterface;
use App\Integrations\Bitrix24\Contracts\BitrixProjectRepositoryInterface;
use App\Integrations\Bitrix24\Contracts\BitrixRecordFetcherInterface;
use App\Integrations\Bitrix24\Contracts\BitrixTaskMapperInterface;
use App\Integrations\Bitrix24\Contracts\BitrixTaskRepositoryInterface;
use App\Integrations\Bitrix24\Contracts\BitrixTimeRepositoryInterface;
use App\Integrations\Bitrix24\Contracts\BitrixUserRepositoryInterface;
use App\Jobs\Bitrix\ImportBitrixChatJob;
use App\Jobs\Bitrix\ImportBitrixChecklistsJob;
use App\Jobs\Bitrix\ImportBitrixFilesJob;
use App\Jobs\Bitrix\ImportBitrixProjectsJob;
use App\Jobs\Bitrix\ImportBitrixResultsJob;
use App\Jobs\Bitrix\ImportBitrixTasksJob;
use App\Jobs\Bitrix\ImportBitrixTimeJob;
use App\Jobs\Bitrix\ImportBitrixUsersJob;
use App\Jobs\Bitrix\SyncBitrixChangesJob;
use App\Models\BitrixSyncCursor;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\BitrixSyncCursorRepositoryInterface;
use App\Services\Contracts\BitrixImportServiceInterface;
use App\Services\Contracts\BitrixReconciliationServiceInterface;
use App\Services\Contracts\BitrixSyncFailureServiceInterface;
use App\Services\Contracts\ProjectServiceInterface;
use App\Services\Contracts\TaskChatServiceInterface;
use App\Services\Contracts\TaskFileServiceInterface;
use App\Services\Contracts\TaskResultServiceInterface;
use App\Services\Contracts\TaskServiceInterface;
use App\Services\Contracts\UserServiceInterface;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class BitrixImportService implements BitrixImportServiceInterface
{
    /** Tasks per chunk of the per-task passes (a Bitrix batch holds 50 commands). */
    private const CHUNK = 50;

    /** Overlap of a changes sync with the previous run, against clock drift between us and Bitrix. */
    private const OVERLAP_SECONDS = 60;

    /** The job that imports each entity. */
    private const JOBS = [
        self::USERS => ImportBitrixUsersJob::class,
        self::PROJECTS => ImportBitrixProjectsJob::class,
        self::TASKS => ImportBitrixTasksJob::class,
        self::CHECKLISTS => ImportBitrixChecklistsJob::class,
        self::TIME => ImportBitrixTimeJob::class,
        self::FILES => ImportBitrixFilesJob::class,
        self::RESULTS => ImportBitrixResultsJob::class,
        self::CHAT => ImportBitrixChatJob::class,
    ];

    public function __construct(
        private BitrixSyncCursorRepositoryInterface $cursors,
        private BitrixUserRepositoryInterface $bitrixUsers,
        private BitrixProjectRepositoryInterface $bitrixProjects,
        private BitrixTaskRepositoryInterface $bitrixTasks,
        private BitrixChecklistRepositoryInterface $bitrixChecklists,
        private BitrixTimeRepositoryInterface $bitrixTime,
        private SyncBitrixUserAction $userSync,
        private SyncBitrixProjectAction $projectSync,
        private SyncBitrixTaskAction $taskSync,
        private SyncBitrixChecklistAction $checklistSync,
        private SyncBitrixTimeLogAction $timeSync,
        private ConnectionInterface $database,
        private BitrixTaskMapperInterface $taskMapper,
        private BitrixRecordFetcherInterface $fetch,
        private BitrixDeletionGuardInterface $deletions,
        private ProjectServiceInterface $projects,
        private TaskServiceInterface $tasks,
        private TaskChatServiceInterface $chat,
        private TaskFileServiceInterface $files,
        private TaskResultServiceInterface $results,
        private BitrixSyncFailureServiceInterface $failures,
        private UserServiceInterface $users,
        private BitrixReconciliationServiceInterface $reconciliation,
    ) {}

    public function queueImport(array $entities, bool $linkByEmail): void
    {
        Bus::chain(array_map(fn (string $entity): object => $this->job($entity, $linkByEmail), $entities))
            ->onQueue(QueueName::Bitrix)
            ->dispatch();
    }

    public function queueChangesSync(): void
    {
        dispatch(new SyncBitrixChangesJob);
    }

    public function runImport(string $entity, bool $linkByEmail, ?Closure $onProgress = null): void
    {
        if ($onProgress instanceof Closure) {
            $this->cursors->watch($entity, $onProgress);
        }

        // Through the job, so a failure is recorded on the cursor row (its failed()) as when queued.
        dispatch_sync($this->job($entity, $linkByEmail));
    }

    public function markFailed(string $entity, ?Throwable $exception, ?string $failedJobUuid = null, ?string $deadLetterSubject = null): void
    {
        // The cursor error shows on the status page too: the same safe text (SEC-5).
        $this->cursors->fail($entity, $this->failures->describe($exception));
        $this->failures->record(BitrixSyncOperation::Import, $deadLetterSubject ?? $entity, $exception, $failedJobUuid);
    }

    public function markChangesSyncFailed(?Throwable $exception, ?string $failedJobUuid): void
    {
        $this->failures->attachFailedJob(BitrixSyncOperation::Import, self::TASKS_CHANGES, $exception, $failedJobUuid);
    }

    public function progress(array $entities): Collection
    {
        return $this->cursors->forEntities($entities);
    }

    public function syncChanges(): ?CarbonImmutable
    {
        $since = $this->lastTaskSync();
        if (! $since instanceof CarbonImmutable) {
            foreach (self::ORDER as $entity) {
                $this->runImport($entity, linkByEmail: false);
            }

            return null;
        }

        dispatch_sync(new ImportBitrixProjectsJob(partOfChangesSync: true));
        dispatch_sync(new ImportBitrixTasksJob($since->subSeconds(self::OVERLAP_SECONDS)));

        return $since;
    }

    public function importUsers(bool $linkByEmail): void
    {
        $cursor = $this->cursors->start(self::USERS);
        $notLinked = 0;
        $seen = [];

        $total = $this->bitrixUsers->eachPage(function (array $rows, int $total) use ($linkByEmail, $cursor, &$notLinked, &$seen): void {
            foreach ($rows as $row) {
                $seen[] = BitrixPayload::of($row, 'user.get')->int('ID');
                if (! $this->database->transaction(fn (): ?User => $this->userSync->handle($row, $linkByEmail)) instanceof User) {
                    $notLinked++;
                }
            }
            $this->cursors->advance($cursor, count($rows), $total);
        });

        $this->reconcile(self::USERS, $seen, fn (): array => $this->users->allBitrixIds());
        $this->complete($cursor);
        Log::info('Bitrix24 users imported.', ['imported' => $cursor->processed, 'not_linked' => $notLinked, 'portal_total' => $total]);
    }

    public function importProjects(bool $reconcile = true): void
    {
        $cursor = $this->cursors->start(self::PROJECTS);
        $skipped = 0;
        $seen = [];

        $total = $this->bitrixProjects->eachGroupPage(function (array $groups, int $total) use ($cursor, &$skipped, &$seen): void {
            if ($groups !== []) {
                $members = $this->bitrixProjects->membersOf($groups);

                foreach ($groups as $group) {
                    $seen[] = $groupId = BitrixPayload::of($group, 'socialnetwork.api.workgroup.list')->int('id');
                    if (! $this->database->transaction(fn (): ?Project => $this->projectSync->handle($group, $members[$groupId] ?? [])) instanceof Project) {
                        $skipped++;
                    }
                }
            }

            $this->cursors->advance($cursor, count($groups), $total);
        });

        [$deleted, $stillInBitrix] = $this->deleteMissingProjects($seen);

        if ($reconcile) {
            // A project deleted here keeps its Bitrix group, closed (step 3.2): not a difference.
            $this->reconcile(self::PROJECTS, [...$seen, ...$stillInBitrix], fn (): array => [
                ...$this->projects->allBitrixGroupIds(),
                ...array_intersect($this->projects->trashedBitrixGroupIds(), $seen),
            ]);
        }
        $this->complete($cursor);
        Log::info('Bitrix24 projects imported.', ['processed' => $cursor->processed, 'skipped' => $skipped, 'deleted' => $deleted, 'portal_total' => $total]);
    }

    public function importTasks(?CarbonImmutable $changedSince = null): void
    {
        $delta = $changedSince instanceof CarbonImmutable;
        $cursor = $this->cursors->start($delta ? self::TASKS_CHANGES : self::TASKS);
        $skipped = 0;
        $seen = [];
        // Totals of the passes already finished; the closed pass's own total
        // is only known once it starts.
        $earlierTotal = 0;

        foreach ($this->bitrixTasks->listFilters($changedSince) as $filter) {
            $earlierTotal += $this->bitrixTasks->eachTaskPage($filter, function (array $rows, int $total) use ($delta, $cursor, $earlierTotal, &$skipped, &$seen): void {
                // A full import runs after the users; a delta may meet someone new.
                if ($delta) {
                    $this->importPeopleOf($rows);
                }

                foreach ($rows as $row) {
                    $seen[] = BitrixPayload::of($row, 'tasks.task.list')->int('id');
                    if (! $this->taskSync->handle($row) instanceof Task) {
                        $skipped++;
                    }
                }

                $this->cursors->advance($cursor, count($rows), $earlierTotal + $total);
            });
        }

        [$deleted, $stillInBitrix] = $delta ? [0, []] : $this->deleteMissingTasks($seen);

        if (! $delta) {
            // Tasks of a project deleted here stay in its closed group in Bitrix: not a difference.
            $this->reconcile(self::TASKS, [...$seen, ...$stillInBitrix], fn (): array => [
                ...$this->tasks->allBitrixIds(),
                ...array_intersect($this->tasks->trashedBitrixIds(), $seen),
            ]);
        }
        $this->complete($cursor);
        Log::info($delta ? 'Bitrix24 task changes synced.' : 'Bitrix24 tasks imported.', [
            'processed' => $cursor->processed, 'skipped' => $skipped, 'deleted' => $deleted, 'portal_total' => $earlierTotal,
        ]);
    }

    public function importChecklists(): void
    {
        $this->eachBatchOfTasks(self::CHECKLISTS, 'Bitrix24 checklists imported.', function (Collection $chunk): int {
            $failed = 0;
            foreach ($this->bitrixChecklists->listMany($this->bitrixIdsOf($chunk)) as $taskId => $rows) {
                // One task Bitrix refuses (e.g. deleted meanwhile) must not stop the rest, nor wipe its list.
                $task = $chunk->firstWhere('id', $taskId);
                $rows === null || ! $task instanceof Task ? $failed++ : $this->checklistSync->handle($task, $rows);
            }

            return $failed;
        });
    }

    public function importTime(): void
    {
        $this->eachBatchOfTasks(self::TIME, 'Bitrix24 time logs imported.', function (Collection $chunk): int {
            $failed = 0;
            foreach ($this->bitrixTime->listMany($this->bitrixIdsOf($chunk)) as $taskId => $rows) {
                // One task Bitrix refuses must not stop the rest, nor wipe its log.
                $task = $chunk->firstWhere('id', $taskId);
                $rows === null || ! $task instanceof Task ? $failed++ : $this->timeSync->handle($task, $rows);
            }

            return $failed;
        });
    }

    public function importFiles(): void
    {
        $cursor = $this->cursors->start(self::FILES);
        $total = $this->tasks->countInBitrix();
        $skipped = 0;

        $this->tasks->chunkInBitrix(self::CHUNK, function (Collection $chunk) use ($cursor, $total, &$skipped): void {
            $skipped += $this->files->refreshManyFromBitrix($chunk);
            $this->cursors->advance($cursor, $chunk->count(), $total);
        });

        $this->complete($cursor);
        Log::info('Bitrix24 task files imported.', ['tasks' => $cursor->processed, 'skipped' => $skipped]);
    }

    public function importResults(): void
    {
        $this->eachTask(self::RESULTS, 'Bitrix24 task results imported.', fn (Task $task) => $this->results->refreshFromBitrix($task));
    }

    public function importChat(): void
    {
        $this->eachTask(self::CHAT, 'Bitrix24 task chats imported.', fn (Task $task) => $this->chat->refreshFromBitrix($task, complete: true));
    }

    /**
     * Step 5.3: compares what the full listing returned with what we hold
     * after the pass. An empty listing is more likely a Bitrix hiccup than an
     * empty portal (the deletions skip it too): no check then.
     *
     * @param  list<int>  $seen  What the listing returned, plus what it missed but Bitrix confirmed still exists
     *                           (changed while the pages were read): Bitrix holds those too.
     * @param  callable(): list<int>  $localIds
     */
    private function reconcile(string $entity, array $seen, callable $localIds): void
    {
        if ($seen !== []) {
            $this->reconciliation->record($entity, $seen, $localIds());
        }
    }

    /** The pass finished: its cursor moves on, and an open failure of it is closed (step 5.2). */
    private function complete(BitrixSyncCursor $cursor): void
    {
        $this->cursors->complete($cursor);
        $this->failures->resolve(BitrixSyncOperation::Import, $cursor->entity);
    }

    private function lastTaskSync(): ?CarbonImmutable
    {
        // The full import and the changes sync each keep their own row: changes are
        // asked for since whichever of them last finished.
        $finished = [];
        foreach ([self::TASKS, self::TASKS_CHANGES] as $entity) {
            if (($at = $this->cursors->forEntity($entity)->last_synced_at) instanceof CarbonImmutable) {
                $finished[] = $at;
            }
        }

        return $finished === [] ? null : max($finished);
    }

    private function job(string $entity, bool $linkByEmail): object
    {
        return $entity === self::USERS ? new ImportBitrixUsersJob($linkByEmail) : new (self::JOBS[$entity]);
    }

    /**
     * Runs $refresh on every Bitrix task. One Bitrix refuses doesn't stop the
     * rest, nor wipe its copy; Bitrix being unreachable stops the whole run.
     *
     * @param  callable(Task): void  $refresh
     */
    private function eachTask(string $entity, string $done, callable $refresh): void
    {
        $this->eachBatchOfTasks($entity, $done, function (Collection $chunk) use ($refresh): int {
            $failed = 0;
            foreach ($chunk as $task) {
                try {
                    $refresh($task);
                } catch (BitrixException $e) {
                    if ($e->errorCode === BitrixException::UNREACHABLE) {
                        throw $e;
                    }
                    $failed++;
                }
            }

            return $failed;
        });
    }

    /**
     * The per-task passes: chunks of Bitrix tasks, with the cursor advanced
     * after each.
     *
     * @param  callable(Collection<int, Task>): int  $chunkDone  Returns how many tasks failed.
     */
    private function eachBatchOfTasks(string $entity, string $done, callable $chunkDone): void
    {
        $cursor = $this->cursors->start($entity);
        $total = $this->tasks->countInBitrix();
        $failed = 0;

        $this->tasks->chunkInBitrix(self::CHUNK, function (Collection $chunk) use ($cursor, $total, $chunkDone, &$failed): void {
            $failed += $chunkDone($chunk);
            $this->cursors->advance($cursor, $chunk->count(), $total);
        });

        $this->complete($cursor);
        Log::info($done, ['tasks' => $cursor->processed, 'failed' => $failed]);
    }

    /**
     * @param  Collection<int, Task>  $chunk
     * @return array<int, int> Bitrix task ID by our task ID.
     */
    private function bitrixIdsOf(Collection $chunk): array
    {
        return $chunk->mapWithKeys(fn (Task $task): array => [$task->id => (int) $task->bitrix_task_id])->all();
    }

    /**
     * People a page of changed tasks points at that we haven't imported yet.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function importPeopleOf(array $rows): void
    {
        $people = [];
        foreach ($rows as $row) {
            $members = $this->taskMapper->members($row);
            array_push($people, $this->taskMapper->creatorBitrixId($row), $this->taskMapper->responsibleBitrixId($row), ...$members[TaskMemberRole::Accomplice->value] ?? [], ...$members[TaskMemberRole::Auditor->value] ?? []);
        }
        $this->importMissingUsers($people);
    }

    /**
     * People a record points at that we haven't imported yet, each saved in
     * its own transaction.
     *
     * @param  list<int>  $bitrixIds
     */
    private function importMissingUsers(array $bitrixIds): void
    {
        foreach ($this->fetch->missingUsers($bitrixIds) as $row) {
            $this->database->transaction(fn (): ?User => $this->userSync->handle($row));
        }
    }

    /**
     * Step 3.6: deletes our copies of groups gone from Bitrix (their delete
     * event may have been lost), once Bitrix confirms each is gone (see
     * BitrixDeletionGuard).
     *
     * @param  list<int>  $seen
     * @return array{int, list<int>} How many were deleted, and the missing ones Bitrix confirmed are still there.
     */
    private function deleteMissingProjects(array $seen): array
    {
        // An empty listing is more likely a Bitrix hiccup than an empty portal.
        if ($seen === []) {
            return [0, []];
        }

        $missing = array_values(array_diff($this->projects->allBitrixGroupIds(), $seen));

        $stillThere = [];
        $gone = $this->deletions->confirmedGone($missing, function (int $id) use (&$stillThere): bool {
            $exists = $this->fetch->group($id) !== null;
            if ($exists) {
                $stillThere[] = $id;
            }

            return $exists;
        }, 'groups');
        $deleted = $this->projects->removeAllGoneFromBitrix($gone);
        $this->deletions->recordDeleted('groups', $gone, 'import');

        return [$deleted, $stillThere];
    }

    /**
     * Deletes our copies of Bitrix tasks the full listing no longer returns,
     * once Bitrix confirms each is gone (see BitrixDeletionGuard).
     *
     * @param  list<int>  $seen
     * @return array{int, list<int>} How many were deleted, and the missing ones Bitrix confirmed are still there.
     */
    private function deleteMissingTasks(array $seen): array
    {
        // An empty listing is more likely a Bitrix hiccup than an empty portal.
        if ($seen === []) {
            return [0, []];
        }

        // Compared here, not in SQL: a NOT IN with every Bitrix ID outgrows the placeholder limit.
        $missing = array_values(array_diff($this->tasks->allBitrixIds(), $seen));

        $stillThere = [];
        $gone = $this->deletions->confirmedGone($missing, function (int $id) use (&$stillThere): bool {
            $exists = $this->fetch->task($id) !== null;
            if ($exists) {
                $stillThere[] = $id;
            }

            return $exists;
        }, 'tasks');
        $deleted = $this->tasks->removeAllGoneFromBitrix($gone);
        $this->deletions->recordDeleted('tasks', $gone, 'import');

        return [$deleted, $stillThere];
    }
}
