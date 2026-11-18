<?php

declare(strict_types=1);

namespace App\Exceptions\TaskLookup;

use App\Exceptions\BusinessException;

/**
 * Statuses and priorities that come from Bitrix24 are managed there.
 */
final class LookupFromBitrixException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Values that come from Bitrix24 cannot be deleted.');
    }
}
