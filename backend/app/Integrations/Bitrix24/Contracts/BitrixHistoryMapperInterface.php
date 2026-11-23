<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Contracts;

/**
 * Step 4.8: tasks.task.history.list rows as entries the task page can show
 * (our status keys, people's names, ISO dates).
 */
interface BitrixHistoryMapperInterface
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{id: int, field: string, from: ?string, to: ?string, user: array{id: ?int, name: string}|null, created_at: ?string}>
     */
    public function entries(array $rows): array;
}
