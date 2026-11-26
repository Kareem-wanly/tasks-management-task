<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24;

use App\Integrations\Bitrix24\Contracts\BitrixTaskResolverInterface;
use App\Integrations\Bitrix24\Mappers\BitrixTaskMapper;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Log;

/**
 * A Bitrix24 task row as our columns. Users and projects must be imported
 * first (steps 2.1 and 2.2): a task whose creator or project we don't have is
 * skipped.
 *
 * @phpstan-import-type ResolvedTask from BitrixTaskResolverInterface
 */
final readonly class BitrixTaskResolver implements BitrixTaskResolverInterface
{
    public function __construct(
        private BitrixTaskMapper $mapper,
        private TaskRepositoryInterface $tasks,
        private ProjectRepositoryInterface $projects,
        private UserRepositoryInterface $users,
    ) {}

    public function resolve(array $row): ?array
    {
        $attributes = $this->mapper->attributes($row);
        $context = ['bitrix_task_id' => $attributes['bitrix_task_id']];

        $creatorId = $this->users->idByBitrixId($this->mapper->creatorBitrixId($row));
        if ($creatorId === null) {
            Log::warning('Bitrix24 task skipped: its creator is not imported.', $context);

            return null;
        }

        $groupId = $this->mapper->groupBitrixId($row);
        $projectId = $groupId === 0 ? null : $this->projects->idByBitrixGroupId($groupId);
        if ($groupId !== 0 && $projectId === null) {
            Log::warning('Bitrix24 task skipped: its project is not imported.', $context + ['bitrix_group_id' => $groupId]);

            return null;
        }

        $responsibleBitrixId = $this->mapper->responsibleBitrixId($row);
        $assigneeId = $this->users->idByBitrixId($responsibleBitrixId);
        if ($responsibleBitrixId !== 0 && $assigneeId === null) {
            Log::warning('Bitrix24 task imported without assignee: responsible user is not imported.', $context);
        }

        return [
            ...$attributes,
            'project_id' => $projectId,
            'parent_id' => $this->parentId($row, $context),
            'created_by' => $creatorId,
            'assigned_to' => $assigneeId,
        ];
    }

    /**
     * Step 4.2. A parent we don't have yet (imported later in the same run)
     * is left empty; the next sync of this task links it.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $row
     */
    private function parentId(array $row, array $context): ?int
    {
        $bitrixId = $this->mapper->parentBitrixId($row);
        if ($bitrixId === 0) {
            return null;
        }

        $parentId = $this->tasks->idByBitrixId($bitrixId);
        if ($parentId === null) {
            Log::warning('Bitrix24 subtask imported without its parent: the parent is not imported yet.', $context + ['bitrix_parent_id' => $bitrixId]);
        }

        return $parentId;
    }
}
