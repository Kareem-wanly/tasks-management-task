<?php

declare(strict_types=1);

namespace App\Enums;

/** bitrix_sync_cursors.status: where one import (or the periodic changes sync) stands. */
enum SyncCursorStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
