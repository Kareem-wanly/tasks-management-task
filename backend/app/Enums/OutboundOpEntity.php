<?php

declare(strict_types=1);

namespace App\Enums;

/** bitrix_outbound_ops.entity: the kind of Bitrix24 record we changed. */
enum OutboundOpEntity: string
{
    case Task = 'task';
    case Project = 'project';
}
