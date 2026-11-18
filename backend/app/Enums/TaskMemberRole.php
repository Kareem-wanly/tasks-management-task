<?php

declare(strict_types=1);

namespace App\Enums;

/** task_members.role. */
enum TaskMemberRole: string
{
    /** Works on the task besides the responsible person. */
    case Accomplice = 'accomplice';

    /** Follows the task. */
    case Auditor = 'auditor';
}
