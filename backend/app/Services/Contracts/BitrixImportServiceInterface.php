<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use Carbon\CarbonImmutable;

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
}
