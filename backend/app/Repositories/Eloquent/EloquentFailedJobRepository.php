<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\FailedJobRepositoryInterface;
use Illuminate\Database\ConnectionInterface;

/** failed_jobs has no model: Laravel's queue writes it. */
final readonly class EloquentFailedJobRepository implements FailedJobRepositoryInterface
{
    public function __construct(private ConnectionInterface $database) {}

    public function countOnQueue(string $queue): int
    {
        return $this->database->table('failed_jobs')->where('queue', $queue)->count();
    }
}
