<?php

declare(strict_types=1);

namespace App\Services\Contracts;

/**
 * Bitrix24 → here: brings our copy of a record up to date after Bitrix told
 * us (an event) that it changed. The queued jobs call it; each method is safe
 * to run twice (QUE-2).
 */
interface BitrixSyncServiceInterface
{
    /**
     * A task changed, was added or was deleted in Bitrix. Our own change coming
     * back (its echo) is recognised and left alone; Bitrix wins otherwise.
     */
    public function syncTask(int $bitrixTaskId): void;

    /** The same for a workgroup (our project). */
    /**
     * Saves the task as Bitrix now has it, importing members we don't know
     * yet. Used after our own member changes: Bitrix adds people of its own
     * (a delegator who did not create the task becomes an auditor).
     */
    public function refreshTask(int $bitrixTaskId): void;

    public function syncProject(int $bitrixGroupId): void;

    /** A message was posted in a task's chat. */
    public function syncTaskChat(int $bitrixTaskId): void;
}
