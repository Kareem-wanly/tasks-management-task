<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * activity_logs.action. The project values carry no prefix: they were stored
 * that way from the start, and stored rows keep their value.
 */
enum ActivityAction: string
{
    case ProjectCreated = 'created';
    case ProjectUpdated = 'updated';
    case ProjectDeleted = 'deleted';
    case MemberAdded = 'member_added';
    case MemberRemoved = 'member_removed';
    case TaskCreated = 'task_created';
    case TaskUpdated = 'task_updated';
    case TaskDeleted = 'task_deleted';
    case BitrixConflict = 'bitrix_conflict';
}
