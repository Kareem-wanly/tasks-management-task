<?php

namespace App\Integrations\Bitrix24;

use App\Models\ActivityLog;
use App\Models\BitrixOutboundOp;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
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
     * @param  array{accomplice: list<int>, auditor: list<int>}|null  $members  Its accomplices and auditors (Bitrix IDs).
     * @return bool True when the event is the echo of our change: nothing to apply.
     */
    public function isTaskEcho(int $bitrixTaskId, ?array $attributes, ?array $members = null): bool
    {
        $ops = BitrixOutboundOp::pending(BitrixOutboundOp::TASK, $bitrixTaskId);
        if ($ops->isEmpty()) {
            return false;
        }

        $fingerprint = $attributes === null
            ? null
            : BitrixFingerprint::task((new Task)->forceFill($attributes), $members ?? ['accomplice' => [], 'auditor' => []]);

        return $this->settle($ops, $fingerprint, Task::withTrashed()->where('bitrix_task_id', $bitrixTaskId)->first());
    }

    /**
     * @param  array<string, mixed>|null  $attributes  Our columns for the fetched group; null when it is gone from Bitrix.
     */
    public function isProjectEcho(int $bitrixGroupId, ?array $attributes, bool $closed): bool
    {
        $ops = BitrixOutboundOp::pending(BitrixOutboundOp::PROJECT, $bitrixGroupId);
        if ($ops->isEmpty()) {
            return false;
        }

        $fingerprint = $attributes === null
            ? null
            : BitrixFingerprint::project((new Project)->forceFill([...$attributes, 'status' => $closed ? 'archived' : 'active']));

        // Deleting here archives the group, so a closed group answers our delete.
        $answers = fn (BitrixOutboundOp $op) => $op->isDelete() && $closed;

        return $this->settle($ops, $fingerprint, Project::withTrashed()->where('bitrix_group_id', $bitrixGroupId)->first(), $answers);
    }

    /**
     * The record as Bitrix has it is our echo when it matches one of our
     * waiting changes (it and the earlier ones are then confirmed); when it
     * matches none, someone changed it in Bitrix: a conflict.
     *
     * @param  Collection<int, BitrixOutboundOp>  $ops  Newest first.
     * @param  (callable(BitrixOutboundOp): bool)|null  $alsoAnswers  Another way a record can answer an op.
     */
    private function settle(Collection $ops, ?string $fingerprint, Task|Project|null $record, ?callable $alsoAnswers = null): bool
    {
        // A delete is confirmed by the record being gone; anything else by an identical record.
        $answered = $ops->first(fn (BitrixOutboundOp $op) => ($op->isDelete() ? $fingerprint === null : $fingerprint === $op->fingerprint)
            || ($alsoAnswers && $alsoAnswers($op)));

        if ($answered) {
            $answered->confirm();

            return true;
        }

        $newest = $ops->first();
        $newest->markConflict();
        $this->logConflict($newest, $record);

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
