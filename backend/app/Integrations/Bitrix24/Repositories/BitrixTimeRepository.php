<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Repositories;

use App\Integrations\Bitrix24\BitrixPayload;
use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Integrations\Bitrix24\Contracts\BitrixTimeRepositoryInterface;
use App\Models\User;

final readonly class BitrixTimeRepository implements BitrixTimeRepositoryInterface
{
    public function __construct(private BitrixGatewayInterface $bitrix) {}

    public function list(int $bitrixTaskId): array
    {
        return BitrixPayload::of($this->bitrix->callAsService('task.elapseditem.getlist', ['TASKID' => $bitrixTaskId]), 'task.elapseditem.getlist')
            ->rows('result');
    }

    public function add(User $actor, int $bitrixTaskId, int $seconds, ?string $comment): void
    {
        $this->bitrix->callAsUser($actor, 'task.elapseditem.add', [
            'TASKID' => $bitrixTaskId,
            'ARFIELDS' => ['SECONDS' => $seconds, 'COMMENT_TEXT' => $comment ?? ''],
        ]);
    }

    public function update(User $actor, int $bitrixTaskId, int $bitrixItemId, int $seconds, ?string $comment): void
    {
        $this->bitrix->callAsUser($actor, 'task.elapseditem.update', [
            'TASKID' => $bitrixTaskId,
            'ITEMID' => $bitrixItemId,
            'ARFIELDS' => ['SECONDS' => $seconds, 'COMMENT_TEXT' => $comment ?? ''],
        ]);
    }

    public function delete(User $actor, int $bitrixTaskId, int $bitrixItemId): void
    {
        $this->bitrix->callAsUser($actor, 'task.elapseditem.delete', ['TASKID' => $bitrixTaskId, 'ITEMID' => $bitrixItemId]);
    }

    public function listMany(array $bitrixTaskIds): array
    {
        $commands = [];
        foreach ($bitrixTaskIds as $taskId => $bitrixTaskId) {
            $commands["task_{$taskId}"] = ['task.elapseditem.getlist', ['TASKID' => $bitrixTaskId]];
        }

        $batch = BitrixPayload::of($this->bitrix->batchAsService($commands), 'task.elapseditem.getlist');

        $rows = [];
        foreach (array_keys($bitrixTaskIds) as $taskId) {
            $rows[$taskId] = $batch->batchRows("task_{$taskId}");
        }

        return $rows;
    }
}
