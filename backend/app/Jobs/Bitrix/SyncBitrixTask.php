<?php

namespace App\Jobs\Bitrix;

use App\Integrations\Bitrix24\BitrixEchoGuard;
use App\Integrations\Bitrix24\BitrixRecordFetcher;
use App\Integrations\Bitrix24\BitrixTaskSync;
use App\Integrations\Bitrix24\Mappers\BitrixTaskMapper;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Step 3.4: brings one Bitrix24 task up to date after a task event (add,
 * update or delete: the event only names the task). Unique until it
 * starts, so the events Bitrix sends for one change collapse into one
 * fetch (debounce).
 */
class SyncBitrixTask implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Seconds before each retry, e.g. while Bitrix is unreachable. */
    public array $backoff = [10, 60];

    /** Safety net: the lock never outlives a stuck job by much. */
    public int $uniqueFor = 120;

    public function __construct(public readonly int $bitrixTaskId)
    {
        $this->onQueue('bitrix');
    }

    public function uniqueId(): string
    {
        return (string) $this->bitrixTaskId;
    }

    public function handle(BitrixRecordFetcher $fetch, BitrixTaskSync $tasks, BitrixTaskMapper $mapper, BitrixEchoGuard $echo): void
    {
        $row = $fetch->task($this->bitrixTaskId);

        // Gone from Bitrix (deleted there): delete it here too.
        if ($row === null) {
            if (! $echo->isTaskEcho($this->bitrixTaskId, null)) {
                Task::where('bitrix_task_id', $this->bitrixTaskId)->first()?->delete();
            }

            return;
        }

        // The task may point at people or a group we haven't seen yet.
        $fetch->importMissingUsers([$mapper->creatorBitrixId($row), $mapper->responsibleBitrixId($row)]);

        $groupId = $mapper->groupBitrixId($row);
        if ($groupId !== 0 && ! Project::where('bitrix_group_id', $groupId)->exists()) {
            app()->call([new SyncBitrixProject($groupId), 'handle']);
        }

        // Our own change coming back: already saved here.
        $attributes = $tasks->resolve($row);
        if ($attributes !== null && $echo->isTaskEcho($this->bitrixTaskId, $attributes)) {
            return;
        }

        $tasks->upsert($row);
    }
}
