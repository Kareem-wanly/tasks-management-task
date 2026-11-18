<?php

namespace App\Jobs\Bitrix;

use App\Integrations\Bitrix24\BitrixDeletionGuard;
use App\Integrations\Bitrix24\BitrixEchoGuard;
use App\Integrations\Bitrix24\BitrixProjectSync;
use App\Integrations\Bitrix24\BitrixRecordFetcher;
use App\Integrations\Bitrix24\Mappers\BitrixProjectMapper;
use App\Models\Project;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Step 3.4: brings one Bitrix24 group up to date after a group event.
 * Unique until it starts, so the events Bitrix sends for one change
 * collapse into one fetch (debounce).
 */
class SyncBitrixProject implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Seconds before each retry, e.g. while Bitrix is unreachable. */
    public array $backoff = [10, 60];

    /** Safety net: the lock never outlives a stuck job by much. */
    public int $uniqueFor = 120;

    public function __construct(public readonly int $bitrixGroupId)
    {
        $this->onQueue('bitrix');
    }

    public function uniqueId(): string
    {
        return (string) $this->bitrixGroupId;
    }

    public function handle(BitrixRecordFetcher $fetch, BitrixProjectSync $projects, BitrixProjectMapper $mapper, BitrixEchoGuard $echo, BitrixDeletionGuard $guard): void
    {
        $group = $fetch->group($this->bitrixGroupId);

        // Gone from Bitrix (deleted there): delete it here too. Only an admin
        // service account sees every group, so "not found" proves nothing otherwise.
        if ($group === null) {
            if (! $echo->isProjectEcho($this->bitrixGroupId, null, false) && $guard->serviceAccountIsAdmin()) {
                if ($project = Project::where('bitrix_group_id', $this->bitrixGroupId)->first()) {
                    $project->delete();
                    $guard->recordDeleted('groups', [$this->bitrixGroupId], 'event');
                }
            }

            return;
        }

        // Our own change coming back: already saved here.
        if ($echo->isProjectEcho($this->bitrixGroupId, $mapper->attributes($group), $mapper->isClosed($group))) {
            return;
        }

        $members = $fetch->members($this->bitrixGroupId);
        $fetch->importMissingUsers([$mapper->ownerBitrixId($group), ...array_keys($mapper->members($members))]);

        $projects->upsert($group, $members);
    }
}
