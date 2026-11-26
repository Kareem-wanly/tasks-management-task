<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Repositories;

use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Integrations\Bitrix24\Contracts\BitrixTimeRepositoryInterface;
use App\Models\User;

final readonly class BitrixTimeRepository implements BitrixTimeRepositoryInterface
{
    public function __construct(private BitrixGatewayInterface $bitrix) {}

    public function list(int $bitrixTaskId): array
    {
        $rows = $this->bitrix->callAsService('task.elapseditem.getlist', ['TASKID' => $bitrixTaskId])['result'] ?? [];

        return is_array($rows) ? array_values($rows) : [];
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
}
