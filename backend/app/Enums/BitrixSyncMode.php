<?php

declare(strict_types=1);

namespace App\Enums;

/** Step 5.1: what the "sync now" buttons ask for. */
enum BitrixSyncMode: string
{
    /** What changed since the last sync, as the 5-minute schedule does. */
    case Changes = 'changes';

    /** Everything, deletions too, as the nightly import does. */
    case Full = 'full';
}
