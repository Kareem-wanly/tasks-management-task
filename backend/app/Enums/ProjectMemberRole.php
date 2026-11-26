<?php

declare(strict_types=1);

namespace App\Enums;

/** project_user.role, as Bitrix24 group roles map to it. */
enum ProjectMemberRole: string
{
    case Owner = 'owner';
    case Moderator = 'moderator';
    case Member = 'member';
}
