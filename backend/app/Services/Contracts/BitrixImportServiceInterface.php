<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\BitrixSyncCursor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Bitrix24 → here, in bulk: the import of every record (first install, the
 * nightly safety net) and the periodic sync of changed tasks. Each method
 * reports its progress to a bitrix_sync_cursors row and is safe to run
 * again (QUE-2): a failed import is simply run again.
 */
interface BitrixImportServiceInterface
{
    public const USERS = 'users';

    public const PROJECTS = 'projects';

    public const TASKS = 'tasks';

    /** The periodic changes sync has its own row, so it never overwrites the full import's. */
    public const TASKS_CHANGES = 'tasks-changes';

    public const CHECKLISTS = 'checklists';

    public const TIME = 'time';

    public const FILES = 'files';

    public const RESULTS = 'results';

    public const CHAT = 'chat';

    /** What a full import brings in, in the order it must run (tasks need users and projects first). */
    public const ORDER = [self::USERS, self::PROJECTS, self::TASKS, self::CHECKLISTS, self::TIME, self::FILES, self::RESULTS, self::CHAT];

    /**
     * Queues the import of the given entities as one chain: it stops at the
     * first failure, so tasks never run on missing users or projects.
     *
     * @param  list<string>  $entities  In ORDER.
     */
    public function queueImport(array $entities, bool $linkByEmail): void;

    /** Imports one entity now, in this process (its failure is recorded on its cursor row, then thrown). */
    public function runImport(string $entity, bool $linkByEmail): void;

    /**
     * Where each entity's last import stands.
     *
     * @param  list<string>  $entities
     * @return Collection<string, BitrixSyncCursor> By entity; entities never imported are missing.
     */
    public function progress(array $entities): Collection;

    /** When the last task import or changes sync started; null when tasks were never imported. */
    public function lastTaskSync(): ?CarbonImmutable;

    /** The periodic sync: every project (no reliable change filter for groups), and the tasks changed since $since. */
    public function syncChanges(CarbonImmutable $since): void;

    /** Every employee; with $linkByEmail an unlinked local account with the same email is linked. */
    public function importUsers(bool $linkByEmail): void;

    /** Every workgroup and its members; groups gone from Bitrix are deleted here (safely). */
    public function importProjects(): void;

    /**
     * Every task (open ones first), deleting the ones gone from Bitrix (safely);
     * or, with $changedSince, only the tasks changed since then (nothing deleted).
     */
    public function importTasks(?CarbonImmutable $changedSince = null): void;

    public function importChecklists(): void;

    public function importTime(): void;

    public function importFiles(): void;

    public function importResults(): void;

    public function importChat(): void;

    /** Records a failed run on its cursor row (from the job's failed()); a re-run starts it again. */
    public function markFailed(string $entity, string $error): void;
}
