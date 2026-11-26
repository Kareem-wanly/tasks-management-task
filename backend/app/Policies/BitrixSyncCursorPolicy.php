<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/** Step 5.1: the sync status page and its "sync now" buttons. Administrators pass Gate::before. */
class BitrixSyncCursorPolicy
{
    public const PERMISSION = 'bitrix_sync.manage';

    public function manage(User $user): bool
    {
        return $user->hasPermission(self::PERMISSION);
    }
}
