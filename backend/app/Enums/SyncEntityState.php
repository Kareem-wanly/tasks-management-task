<?php

declare(strict_types=1);

namespace App\Enums;

/** Step 5.1: one imported entity as the sync status page shows it. */
enum SyncEntityState: string
{
    case Never = 'never';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    /** Still marked running long after the job's time limit: the worker died mid-run. */
    case Stalled = 'stalled';
}
