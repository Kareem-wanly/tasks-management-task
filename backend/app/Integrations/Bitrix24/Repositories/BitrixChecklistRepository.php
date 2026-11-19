<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Repositories;

use App\Integrations\Bitrix24\Contracts\BitrixChecklistRepositoryInterface;
use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Models\User;

final readonly class BitrixChecklistRepository implements BitrixChecklistRepositoryInterface
{
    public function __construct(private BitrixGatewayInterface $bitrix) {}

    public function list(int $bitrixTaskId): array
    {
        $rows = $this->bitrix->callAsService('task.checklistitem.getlist', [
            'TASKID' => $bitrixTaskId,
            'ORDER' => ['SORT_INDEX' => 'asc'],
        ])['result'] ?? [];

        return is_array($rows) ? array_values($rows) : [];
    }

    public function add(User $actor, int $bitrixTaskId, string $title, ?int $bitrixListId): void
    {
        $fields = ['TITLE' => $title];
        if ($bitrixListId) {
            $fields['PARENT_ID'] = $bitrixListId;
        }

        $this->call($actor, $bitrixTaskId, 'task.checklistitem.add', ['FIELDS' => $fields]);
    }

    public function rename(User $actor, int $bitrixTaskId, int $bitrixItemId, string $title): void
    {
        $this->call($actor, $bitrixTaskId, 'task.checklistitem.update', ['ITEMID' => $bitrixItemId, 'FIELDS' => ['TITLE' => $title]]);
    }

    public function setComplete(User $actor, int $bitrixTaskId, int $bitrixItemId, bool $complete): void
    {
        $this->call($actor, $bitrixTaskId, $complete ? 'task.checklistitem.complete' : 'task.checklistitem.renew', ['ITEMID' => $bitrixItemId]);
    }

    public function delete(User $actor, int $bitrixTaskId, int $bitrixItemId): void
    {
        $this->call($actor, $bitrixTaskId, 'task.checklistitem.delete', ['ITEMID' => $bitrixItemId]);
    }

    /**
     * Bitrix only moves "after an item" (after the list itself would take the
     * item out of it), so first place is two moves: after the current first
     * item, then that one after it.
     */
    public function move(User $actor, int $bitrixTaskId, int $bitrixItemId, ?int $afterItemId, ?int $firstItemId): void
    {
        if ($afterItemId !== null) {
            $this->moveAfter($actor, $bitrixTaskId, $bitrixItemId, $afterItemId);

            return;
        }

        if ($firstItemId !== null) {
            $this->moveAfter($actor, $bitrixTaskId, $bitrixItemId, $firstItemId);
            $this->moveAfter($actor, $bitrixTaskId, $firstItemId, $bitrixItemId);
        }
    }

    private function moveAfter(User $actor, int $bitrixTaskId, int $bitrixItemId, int $afterItemId): void
    {
        $this->call($actor, $bitrixTaskId, 'task.checklistitem.moveafteritem', ['ITEMID' => $bitrixItemId, 'AFTERITEMID' => $afterItemId]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function call(User $actor, int $bitrixTaskId, string $method, array $params): void
    {
        $this->bitrix->callAsUser($actor, $method, ['TASKID' => $bitrixTaskId, ...$params]);
    }
}
