<?php

declare(strict_types=1);

namespace App\Enums;

/** projects.status. Archived is set by archiving (or a closed Bitrix24 group), never chosen in the form. */
enum ProjectStatus: string
{
    case Active = 'active';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Archived = 'archived';

    /** @return list<string> The statuses a project form may set. */
    public static function choosable(): array
    {
        return [self::Active->value, self::InProgress->value, self::OnHold->value, self::Completed->value];
    }
}
