<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\QueueName;
use App\Enums\TaskMemberRole;
use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\BitrixChecklistSync;
use App\Integrations\Bitrix24\BitrixDeletionGuard;
use App\Integrations\Bitrix24\BitrixProjectSync;
use App\Integrations\Bitrix24\BitrixRecordFetcher;
use App\Integrations\Bitrix24\BitrixTaskSync;
use App\Integrations\Bitrix24\BitrixTimeSync;
use App\Integrations\Bitrix24\BitrixUserSync;
use App\Integrations\Bitrix24\Contracts\BitrixChecklistRepositoryInterface;
use App\Integrations\Bitrix24\Contracts\BitrixProjectRepositoryInterface;
use App\Integrations\Bitrix24\Contracts\BitrixTaskRepositoryInterface;
use App\Integrations\Bitrix24\Contracts\BitrixTimeRepositoryInterface;
use App\Integrations\Bitrix24\Contracts\BitrixUserRepositoryInterface;
use App\Integrations\Bitrix24\Mappers\BitrixTaskMapper;
use App\Jobs\Bitrix\ImportBitrixChat;
use App\Jobs\Bitrix\ImportBitrixChecklists;
use App\Jobs\Bitrix\ImportBitrixFiles;
use App\Jobs\Bitrix\ImportBitrixProjects;
use App\Jobs\Bitrix\ImportBitrixResults;
use App\Jobs\Bitrix\ImportBitrixTasks;
use App\Jobs\Bitrix\ImportBitrixTime;
use App\Jobs\Bitrix\ImportBitrixUsers;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\BitrixSyncCursorRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\Contracts\BitrixImportServiceInterface;
use App\Services\Contracts\TaskChatServiceInterface;
use App\Services\Contracts\TaskFileServiceInterface;
use App\Services\Contracts\TaskResultServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

final readonly class BitrixImportService implements BitrixImportServiceInterface
{
    /** Tasks per chunk of the per-task passes (a Bitrix batch holds 50 commands). */
    private const CHUNK = 50;

    /** Overlap of a changes sync with the previous run, against clock drift between us and Bitrix. */
    private const OVERLAP_SECONDS = 60;

    /** The job that imports each entity. */
    private const JOBS = [
        self::USERS => ImportBitrixUsers::class,
        self::PROJECTS => ImportBitrixProjects::class,
        self::TASKS => ImportBitrixTasks::class,
        self::CHECKLISTS => ImportBitrixChecklists::class,
        self::TIME => ImportBitrixTime::class,
        self::FILES => ImportBitrixFiles::class,
        self::RESULTS => ImportBitrixResults::class,
        self::CHAT => ImportBitrixChat::class,
    ];

    public function __construct(
        private BitrixSyncCursorRepositoryInterface $cursors,
        private BitrixUserRepositoryInterface $bitrixUsers,
        private BitrixProjectRepositoryInterface $bitrixProjects,
        private BitrixTaskRepositoryInterface $bitrixTasks,
        private BitrixChecklistRepositoryInterface $bitrixChecklists,
        private BitrixTimeRepositoryInterface $bitrixTime,
        private BitrixUserSync $userSync,
        private BitrixProjectSync $projectSync,
        private BitrixTaskSync $taskSync,
        private BitrixChecklistSync $checklistSync,
        private BitrixTimeSync $timeSync,
        private BitrixTaskMapper $taskMapper,
        private BitrixRecordFetcher $fetch,
        private BitrixDeletionGuard $deletions,
        private ProjectRepositoryInterface $projects,
        private TaskRepositoryInterface $tasks,
        private TaskChatServiceInterface $chat,
        private TaskFileServiceInterface $files,
        private TaskResultServiceInterface $results,
    ) {}

    public function queueImport(array $entities, bool $linkByEmail): void
    {
        Bus::chain(array_map(fn (string $entity): object => $this->job($entity, $linkByEmail), $entities))
            ->onQueue(QueueName::Bitrix)
            ->dispatch();
    }

    public function runImport(string $entity, bool $linkByEmail): void
    {
        // Through the job, so a failure is recorded on the cursor row (its failed()) as when queued.
        dispatch_sync($this->job($entity, $linkByEmail));
    }

    public function progress(array $entities): Collection
    {
        return $this->cursors->forEntities($entities);
    }

    public function lastTaskSync(): ?CarbonImmutable
    {
        // The full import and the changes sync each keep their own row: changes are
        // asked for since whichever of them last finished.
        $last = collect([self::TASKS, self::TASKS_CHANGES])
            ->map(fn (string $entity) => $this->cursors->forEntity($entity)->last_synced_at)
            ->filter()
            ->max();

        return $last?->toImmutable();
    }

    public function syncChanges(CarbonImmutable $since): void
    {
        dispatch_sync(new ImportBitrixProjects);
        dispatch_sync(new ImportBitrixTasks($since->subSeconds(self::OVERLAP_SECONDS)));
    }

    public function importUsers(bool $linkByEmail): void
    {
        $cursor = $this->cursors->start(self::USERS);
        $notLinked = 0;

        $total = $this->bitrixUsers->eachPage(function (array $rows, int $total) use ($linkByEmail, $cursor, &$notLinked): void {
            foreach ($rows as $row) {
                if (! $this->userSync->upsert($row, $linkByEmail) instanceof User) {
                    $notLinked++;
                }
            }
            $this->cursors->advance($cursor, count($rows), $total);
        });

        $this->cursors->complete($cursor);
        Log::info('Bitrix24 users imported.', ['imported' => $cursor->processed, 'not_linked' => $notLinked, 'portal_total' => $total]);
    }

    public function importProjects(): void
    {
        $cursor = $this->cursors->start(self::PROJECTS);
        $skipped = 0;
        $seen = [];

        $total = $this->bitrixProjects->eachGroupPage(function (array $page, int $total) use ($cursor, &$skipped, &$seen): void {
            $groups = $page['workgroups'] ?? [];

            if ($groups !== []) {
                $members = $this->bitrixProjects->membersOf($groups);

                foreach ($groups as $group) {
                    $seen[] = (int) $group['id'];
                    if (! $this->projectSync->upsert($group, $members[(int) $group['id']]) instanceof Project) {
                        $skipped++;
                    }
                }
            }

            $this->cursors->advance($cursor, count($groups), $total);
        });

        $deleted = $this->deleteMissingProjects($seen);

        $this->cursors->complete($cursor);
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
            $earlierTotal += $this->bitrixTasks->eachTaskPage($filter, function (array $page, int $total) use ($delta, $cursor, $earlierTotal, &$skipped, &$seen): void {
                $rows = $page['tasks'] ?? [];

                // A full import runs after the users; a delta may meet someone new.
                if ($delta) {
                    $this->importPeopleOf($rows);
                }

                foreach ($rows as $row) {
                    $seen[] = (int) $row['id'];
                    if (! $this->taskSync->upsert($row) instanceof Task) {
                        $skipped++;
                    }
                }

                $this->cursors->advance($cursor, count($rows), $earlierTotal + $total);
            });
        }

        $deleted = $delta ? 0 : $this->deleteMissingTasks($seen);

        $this->cursors->complete($cursor);
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
                $rows === null ? $failed++ : $this->checklistSync->sync($chunk->firstWhere('id', $taskId), $rows);
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
                $rows === null ? $failed++ : $this->timeSync->sync($chunk->firstWhere('id', $taskId), $rows);
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

        $this->cursors->complete($cursor);
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

    private function job(string $entity, bool $linkByEmail): object
    {
        return $entity === self::USERS ? new ImportBitrixUsers($linkByEmail) : new (self::JOBS[$entity]);
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

        $this->cursors->complete($cursor);
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
        $this->fetch->importMissingUsers($people);
    }

    /**
     * Step 3.6: deletes our copies of groups gone from Bitrix (their delete
     * event may have been lost), once Bitrix confirms each is gone (see
     * BitrixDeletionGuard).
     *
     * @param  list<int>  $seen
     */
    private function deleteMissingProjects(array $seen): int
    {
        // An empty listing is more likely a Bitrix hiccup than an empty portal.
        if ($seen === []) {
            return 0;
        }

        $missing = array_values(array_diff($this->projects->allBitrixIds(), $seen));

        $gone = $this->deletions->confirmedGone($missing, fn (int $id): bool => $this->fetch->group($id) !== null, 'groups');
        $deleted = $this->projects->deleteByBitrixIds($gone);
        $this->deletions->recordDeleted('groups', $gone, 'import');

        return $deleted;
    }

    /**
     * Deletes our copies of Bitrix tasks the full listing no longer returns,
     * once Bitrix confirms each is gone (see BitrixDeletionGuard).
     *
     * @param  list<int>  $seen
     */
    private function deleteMissingTasks(array $seen): int
    {
        // An empty listing is more likely a Bitrix hiccup than an empty portal.
        if ($seen === []) {
            return 0;
        }

        // Compared here, not in SQL: a NOT IN with every Bitrix ID outgrows the placeholder limit.
        $missing = array_values(array_diff($this->tasks->allBitrixIds(), $seen));

        $gone = $this->deletions->confirmedGone($missing, fn (int $id): bool => $this->fetch->task($id) !== null, 'tasks');
        $deleted = $this->tasks->deleteByBitrixIds($gone);
        $this->deletions->recordDeleted('tasks', $gone, 'import');

        return $deleted;
    }
}
