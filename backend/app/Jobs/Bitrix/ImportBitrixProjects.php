<?php

declare(strict_types=1);

namespace App\Jobs\Bitrix;

use App\Enums\QueueName;
use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\BitrixDeletionGuard;
use App\Integrations\Bitrix24\BitrixProjectSync;
use App\Integrations\Bitrix24\BitrixRecordFetcher;
use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Integrations\Bitrix24\Mappers\BitrixProjectMapper;
use App\Repositories\Contracts\BitrixSyncCursorRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Step 2.2: copies every workgroup (groups, projects, scrum, collabs) into
 * projects, with members and roles. Run after ImportBitrixUsers.
 * Safe to re-run: rows are matched on bitrix_group_id.
 */
class ImportBitrixProjects implements ShouldQueue
{
    use Queueable, TracksSyncProgress;

    public const ENTITY = 'projects';

    public function __construct()
    {
        $this->onQueue(QueueName::Bitrix);
    }

    public function handle(BitrixGatewayInterface $bitrix, BitrixProjectSync $projects, BitrixSyncCursorRepositoryInterface $cursors, ProjectRepositoryInterface $projectRepository, BitrixRecordFetcher $fetch, BitrixDeletionGuard $guard): void
    {
        $cursor = $cursors->start(self::ENTITY);
        $skipped = 0;
        $seen = [];

        $total = $bitrix->eachPageAsService('socialnetwork.api.workgroup.list', [
            'select' => BitrixProjectMapper::SELECT,
            'order' => ['ID' => 'ASC'],
            // Without it the list is limited to groups the service user can see.
            'params' => ['IS_ADMIN' => 'Y'],
        ], function (array $page, int $total) use ($bitrix, $projects, $cursor, $cursors, &$skipped, &$seen): void {
            $groups = $page['workgroups'] ?? [];

            if ($groups !== []) {
                $members = $this->members($bitrix, $groups);

                foreach ($groups as $group) {
                    $seen[] = (int) $group['id'];
                    if (! $projects->upsert($group, $members[(int) $group['id']]) instanceof \App\Models\Project) {
                        $skipped++;
                    }
                }
            }

            $cursors->advance($cursor, count($groups), $total);
        });

        $deleted = $this->deleteMissing($projectRepository, $seen, $fetch, $guard);

        $cursors->complete($cursor);
        Log::info('Bitrix24 projects imported.', ['processed' => $cursor->processed, 'skipped' => $skipped, 'deleted' => $deleted, 'portal_total' => $total]);
    }

    /**
     * Step 3.6: deletes our copies of groups gone from Bitrix (their delete
     * event may have been lost), once Bitrix confirms each is gone (see
     * BitrixDeletionGuard).
     *
     * @param  list<int>  $seen
     */
    private function deleteMissing(ProjectRepositoryInterface $projects, array $seen, BitrixRecordFetcher $fetch, BitrixDeletionGuard $guard): int
    {
        // An empty listing is more likely a Bitrix hiccup than an empty portal.
        if ($seen === []) {
            return 0;
        }

        $missing = array_values(array_diff($projects->allBitrixIds(), $seen));

        $gone = $guard->confirmedGone($missing, fn (int $id): bool => $fetch->group($id) !== null, 'groups');
        $deleted = $projects->deleteByBitrixIds($gone);
        $guard->recordDeleted('groups', $gone, 'import');

        return $deleted;
    }

    /**
     * Members of one page of groups (at most 50), in a single batch request.
     *
     * @return array<int, array> group ID => sonet_group.user.get result
     */
    private function members(BitrixGatewayInterface $bitrix, array $groups): array
    {
        $commands = [];
        foreach ($groups as $group) {
            $commands["group_{$group['id']}"] = ['sonet_group.user.get', ['ID' => $group['id']]];
        }

        $batch = $bitrix->batchAsService($commands);

        // A group without its members would wipe them here; fail instead.
        if (! empty($batch['result_error'])) {
            throw BitrixException::fromResponse(reset($batch['result_error']), 200);
        }

        $members = [];
        foreach ($groups as $group) {
            $members[(int) $group['id']] = $batch['result']["group_{$group['id']}"] ?? [];
        }

        return $members;
    }
}
