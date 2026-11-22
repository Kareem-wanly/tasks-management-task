<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Repositories;

use App\Integrations\Bitrix24\BitrixPayload;
use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Integrations\Bitrix24\Contracts\BitrixResultRepositoryInterface;
use App\Models\User;

final readonly class BitrixResultRepository implements BitrixResultRepositoryInterface
{
    /** tasks.task.result.list returns up to this many per call. */
    private const LIST_LIMIT = 1000;

    public function __construct(private BitrixGatewayInterface $bitrix) {}

    public function list(int $bitrixTaskId): array
    {
        return BitrixPayload::of($this->bitrix->callV3AsService('tasks.task.result.list', [
            'filter' => [['taskId', $bitrixTaskId]],
            'select' => ['id', 'taskId', 'text', 'authorId', 'createdAt', 'updatedAt', 'status'],
            'order' => ['createdAt' => 'DESC'],
            'pagination' => ['limit' => self::LIST_LIMIT],
        ]), 'tasks.task.result.list')->rows('result.items');
    }

    public function add(User $actor, int $bitrixTaskId, string $text): void
    {
        $this->bitrix->callV3AsUser($actor, 'tasks.task.result.add', ['fields' => ['taskId' => $bitrixTaskId, 'text' => $text]]);
    }

    public function update(User $actor, int $bitrixResultId, string $text): void
    {
        $this->bitrix->callV3AsUser($actor, 'tasks.task.result.update', ['id' => $bitrixResultId, 'fields' => ['text' => $text]]);
    }

    public function delete(User $actor, int $bitrixResultId): void
    {
        $this->bitrix->callV3AsUser($actor, 'tasks.task.result.delete', ['id' => $bitrixResultId]);
    }
}
