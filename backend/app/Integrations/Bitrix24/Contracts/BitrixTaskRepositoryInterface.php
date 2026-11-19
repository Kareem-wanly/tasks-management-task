<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Contracts;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Tasks in Bitrix24 (tasks.task.*), through the gateway, with the acting
 * employee's token so Bitrix applies their permissions and shows the change
 * under their name. Reads and writes only; TaskService and TaskMemberService
 * decide when and whether.
 *
 * Values are keyed by our task columns, with every link already turned into
 * its Bitrix ID: priority (Bitrix priority), assigned_to (Bitrix user),
 * parent_id (Bitrix task, 0 for none).
 */
interface BitrixTaskRepositoryInterface
{
    /** Bitrix STATUS "Pending", the status of a new task. */
    public const STATUS_PENDING = 2;

    /** Bitrix PRIORITY "Normal". */
    public const DEFAULT_PRIORITY = 1;

    /**
     * Our columns Bitrix keeps, in the order they are sent.
     *
     * @return list<string>
     */
    public function columns(): array;

    /**
     * Which of the given columns Bitrix keeps, in the given order.
     *
     * @param  list<string>  $columns
     * @return list<string>
     */
    public function syncedColumns(array $columns): array;

    /**
     * @param  array<string, mixed>  $values
     * @return array{id: int, status: int} The new task's Bitrix ID and the status Bitrix gave it.
     */
    public function create(User $actor, array $values, int $groupId): array;

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(User $actor, int $bitrixTaskId, array $values): void;

    public function delete(User $actor, int $bitrixTaskId): void;

    /**
     * Moves the task from $current towards $target through Bitrix's status
     * actions (STATUS can't be written).
     *
     * @return int The status Bitrix ended on (it may stop before $target).
     */
    public function changeStatus(User $actor, int $bitrixTaskId, int $current, int $target): int;

    /** Legacy "New" behaves like "Pending". */
    public function normalizeStatus(int $status): int;

    /**
     * Replaces both lists (an empty list clears it).
     *
     * @param  list<int>  $accompliceIds  Bitrix user IDs
     * @param  list<int>  $auditorIds  Bitrix user IDs
     */
    public function setMembers(User $actor, int $bitrixTaskId, array $accompliceIds, array $auditorIds): void;

    /** The actor starts or stops following the task (becomes or stops being an auditor). */
    public function watch(User $actor, int $bitrixTaskId, bool $watch): void;

    /** Hands the task to another responsible person. */
    public function delegate(User $actor, int $bitrixTaskId, int $toBitrixUserId): void;

    /**
     * The task's change history, newest first, read with the service account.
     *
     * @return list<array<string, mixed>>
     */
    public function history(int $bitrixTaskId): array;

    /**
     * The list passes of an import: open tasks, then closed ones; or, with
     * $changedSince, the tasks changed since then.
     *
     * @return list<array<string, mixed>>
     */
    public function listFilters(?CarbonImmutable $changedSince): array;

    /**
     * The tasks one filter matches, page by page, read with the service account.
     *
     * @param  array<string, mixed>  $filter
     * @param  callable(array<string, mixed>, int): void  $onPage  The page and the filter's total.
     * @return int The filter's total.
     */
    public function eachTaskPage(array $filter, callable $onPage): int;
}
