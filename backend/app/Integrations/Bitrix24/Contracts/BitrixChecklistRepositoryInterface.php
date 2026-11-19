<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Contracts;

use App\Models\User;

/**
 * A task's checklist in Bitrix24 (task.checklistitem.*), through the gateway.
 * Reads and writes only; TaskChecklistService decides when and whether.
 */
interface BitrixChecklistRepositoryInterface
{
    /**
     * The task's lists and items in Bitrix order, read with the service account.
     *
     * @return list<array<string, mixed>>
     */
    public function list(int $bitrixTaskId): array;

    /** Adds to the given list; without one, Bitrix adds to its first list (creating it when there is none). */
    public function add(User $actor, int $bitrixTaskId, string $title, ?int $bitrixListId): void;

    public function rename(User $actor, int $bitrixTaskId, int $bitrixItemId, string $title): void;

    public function setComplete(User $actor, int $bitrixTaskId, int $bitrixItemId, bool $complete): void;

    public function delete(User $actor, int $bitrixTaskId, int $bitrixItemId): void;

    /**
     * Puts the item right after $afterItemId, or first (before $firstItemId)
     * when there is no item to follow.
     */
    public function move(User $actor, int $bitrixTaskId, int $bitrixItemId, ?int $afterItemId, ?int $firstItemId): void;

    /**
     * The same for many tasks, in one batched request. A task Bitrix refuses
     * (e.g. deleted meanwhile) comes back as null, so its copy is kept.
     *
     * @param  array<int, int>  $bitrixTaskIds  Bitrix task ID by our task ID.
     * @return array<int, list<array<string, mixed>>|null> By our task ID.
     */
    public function listMany(array $bitrixTaskIds): array;
}
