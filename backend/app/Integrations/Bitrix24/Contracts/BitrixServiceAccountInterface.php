<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Contracts;

use App\Exceptions\Bitrix\BitrixTokenMissingException;

/**
 * Whose token background sync uses: the installed portal's (the user who
 * installed the app). Asked on each call, so a reinstall takes effect at once.
 */
interface BitrixServiceAccountInterface
{
    /** @throws BitrixTokenMissingException when the app is not installed. */
    public function holder(): HasBitrixToken;
}
