<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

/**
 * Step 5.1: the failed_jobs table, read for the sync status page.
 */
interface FailedJobRepositoryInterface
{
    public function countOnQueue(string $queue): int;
}
