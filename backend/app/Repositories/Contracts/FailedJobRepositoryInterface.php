<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

/**
 * Laravel's failed_jobs table. Step 5.2 keeps its own dead letters
 * (bitrix_sync_failures) and forgets the matching failed_jobs row once
 * one is retried or closed, so `queue:retry all` can't run it twice.
 */
interface FailedJobRepositoryInterface
{
    public function forget(string $uuid): void;
}
