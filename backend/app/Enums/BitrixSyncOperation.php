<?php

declare(strict_types=1);

namespace App\Enums;

/** bitrix_sync_failures.operation: which background Bitrix24 work failed (step 5.2). */
enum BitrixSyncOperation: string
{
    /** One task brought up to date after a Bitrix event; the subject is its Bitrix ID. */
    case Task = 'task';

    /** One group brought up to date after a Bitrix event; the subject is its Bitrix ID. */
    case Project = 'project';

    /** One task's chat after a new message in Bitrix; the subject is the task's Bitrix ID. */
    case TaskChat = 'task_chat';

    /** One import pass; the subject is its entity (users, tasks, tasks-changes, ...). */
    case Import = 'import';
}
