<?php

declare(strict_types=1);

namespace App\Enums;

/** bitrix_outbound_ops.status: our change sent to Bitrix24, and what its echo said. */
enum OutboundOpStatus: string
{
    case Sent = 'sent';
    case Confirmed = 'confirmed';
    case Conflict = 'conflict';
}
