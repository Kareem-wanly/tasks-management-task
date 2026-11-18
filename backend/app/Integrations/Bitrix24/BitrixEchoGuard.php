<?php

namespace App\Integrations\Bitrix24;

use App\Models\ActivityLog;
use App\Models\BitrixOutboundOp;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\Log;

/**
 * Step 3.5: tells an event that only echoes our own change from a real
 * change made in Bitrix. We always fetch the record first, so no change made
 * in Bitrix is skipped: an event is the echo only when the record matches
 * the fingerprint we sent. A different record right after our change is a
 * conflict, logged, and Bitrix's version is applied.
 */
class BitrixEchoGuard
{
    /**
     * @param  array<string, mixed>|null  $attributes  Our columns for the fetched task; null when it is gone from Bitrix.
     * @return bool True when the event is the echo of our change: nothing to apply.
     */
    public function isTaskEcho(int $bitrixTaskId, ?array $attributes): bool
    {
        $op = BitrixOutboundOp::pending(BitrixOutboundOp::TASK, $bitrixTaskId);
        if (! $op) {
            return false;
        }

        $fingerprint = $attributes === null ? null : BitrixFingerprint::task((new Task)->forceFill($attributes));

        return $this->settle($op, $fingerprint, Task::withTrashed()->where('bitrix_task_id', $bitrixTaskId)->first());
    }

    /**
     * @param  array<string, mixed>|null  $attributes  Our columns for the fetched group; null when it is gone from Bitrix.
     */
    public function isProjectEcho(int $bitrixGroupId, ?array $attributes, bool $closed): bool
    {
        $op = BitrixOutboundOp::pending(BitrixOutboundOp::PROJECT, $bitrixGroupId);
        if (! $op) {
            return false;
        }

        // Deleting here archives the group, so a closed group answers our delete.
        if ($op->isDelete() && $closed) {
            $op->confirm();

            return true;
        }

        $fingerprint = $attributes === null
            ? null
            : BitrixFingerprint::project((new Project)->forceFill([...$attributes, 'status' => $closed ? 'archived' : 'active']));

        return $this->settle($op, $fingerprint, Project::withTrashed()->where('bitrix_group_id', $bitrixGroupId)->first());
    }

    private function settle(BitrixOutboundOp $op, ?string $fingerprint, Task|Project|null $record): bool
    {
        // A delete is confirmed by the record being gone; anything else by an identical record.
        if ($op->isDelete() ? $fingerprint === null : $fingerprint === $op->fingerprint) {
            $op->confirm();

            return true;
        }

        $op->markConflict();
        $this->logConflict($op, $record);

        return false;
    }

    private function logConflict(BitrixOutboundOp $op, Task|Project|null $record): void
    {
        $context = ['entity' => $op->entity, 'bitrix_id' => $op->bitrix_id, 'our_action' => $op->action, 'by_user_id' => $op->user_id];
        Log::warning('Bitrix24 conflict: the record changed in Bitrix right after our change; keeping the Bitrix version.', $context);

        if (! $record) {
            return;
        }

        $isTask = $record instanceof Task;
        ActivityLog::create([
            'user_id' => $op->user_id,
            'project_id' => $isTask ? $record->project_id : $record->id,
            'task_id' => $isTask ? $record->id : null,
            'action' => 'bitrix_conflict',
            'description' => ($isTask ? 'Task' : 'Project')." '{$record->title}' was changed in Bitrix24 right after a change here; the Bitrix24 version was kept.",
            'metadata' => $context,
        ]);
    }
}
