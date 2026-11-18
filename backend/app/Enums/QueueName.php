<?php

declare(strict_types=1);

namespace App\Enums;

/** Queue names (QUE-4). Workers run `queue:work --queue=bitrix`. */
enum QueueName: string
{
    case Bitrix = 'bitrix';
}
