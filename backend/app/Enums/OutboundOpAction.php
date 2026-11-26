<?php

declare(strict_types=1);

namespace App\Enums;

/** bitrix_outbound_ops.action: what we sent to Bitrix24. */
enum OutboundOpAction: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
}
