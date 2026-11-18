<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Data\TimeLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Step 4.4: { data: entries, meta: { totals, can_log, timer, stopped_task, stale } }.
 *
 * @property TimeLog $resource
 */
class TimeLogResource extends JsonResource
{
    public static $wrap;

    public function toArray(Request $request): array
    {
        $log = $this->resource;
        $task = $log->task;

        return [
            'data' => TaskElapsedItemResource::collection($log->entries),
            'meta' => [
                'stale' => $log->stale,
                'allow_time_tracking' => (bool) $task->allow_time_tracking,
                'time_estimate' => (int) $task->time_estimate,
                'time_spent' => (int) $task->time_spent,
                'can_log' => $request->user()->can('logTime', $task),
                // The caller's timer, on this task or another.
                'timer' => $log->timer ? [
                    'task_id' => $log->timer->task_id,
                    'task_title' => $log->timer->task?->title,
                    'started_at' => $log->timer->started_at->toISOString(),
                ] : null,
                'stopped_task' => $log->stoppedTask ? ['id' => $log->stoppedTask->id, 'title' => $log->stoppedTask->title] : null,
            ],
        ];
    }
}
