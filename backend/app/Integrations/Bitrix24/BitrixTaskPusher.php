<?php

namespace App\Integrations\Bitrix24;

use App\Models\BitrixOutboundOp;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;

/**
 * Step 3.1: sends a task change made in our app to Bitrix24 with the
 * acting employee's own token, so Bitrix applies their permissions and
 * shows the change under their name. Called before the local save: when
 * Bitrix refuses, it throws and nothing is saved here.
 */
class BitrixTaskPusher
{
    /** Bitrix STATUS values. 1 (New) is legacy and behaves like Pending. */
    private const NEW = 1;

    private const PENDING = 2;

    private const IN_PROGRESS = 3;

    private const AWAITING_CONTROL = 4;

    private const COMPLETED = 5;

    private const DEFERRED = 6;

    private const DECLINED = 7;

    /** Bitrix PRIORITY "Normal". */
    private const DEFAULT_PRIORITY = 1;

    /** Our columns => Bitrix fields that tasks.task.add/update accept. */
    private const FIELDS = [
        'title' => 'TITLE',
        'description' => 'DESCRIPTION',
        'priority' => 'PRIORITY',
        'due_date' => 'DEADLINE',
        'assigned_to' => 'RESPONSIBLE_ID',
    ];

    public function __construct(private readonly BitrixClient $bitrix) {}

    /**
     * Tasks of a project linked to a Bitrix group, and tasks without a
     * project. Local-only projects (old demo data) are not in Bitrix.-
     */
    public function syncs(Task $task): bool
    {
        return $task->project_id === null || $task->project?->bitrix_group_id !== null;
    }

    /**
     * Creates the (unsaved) task in Bitrix and fills in what Bitrix decided:
     * its ID, the responsible person and the status.
     */
    public function create(User $actor, Task $task): void
    {
        if (! $this->syncs($task)) {
            return;
        }

        $this->ensureLinked($actor);

        // A Bitrix task always has a responsible person: default to the creator.
        $task->assigned_to ??= $actor->id;
        // Values the request left out get Bitrix's defaults (Pending, Normal).
        $task->status ??= TaskStatus::keyForBitrix(self::PENDING);
        $task->priority ??= TaskPriority::keyForBitrix(self::DEFAULT_PRIORITY);

        $fields = $this->fields($task, array_keys(self::FIELDS));
        $fields['GROUP_ID'] = $task->project?->bitrix_group_id ?? 0;

        $created = $this->bitrix->callAsUser($actor, 'tasks.task.add', ['fields' => $fields])['result']['task'] ?? [];
        $task->bitrix_task_id = (int) ($created['id'] ?? 0) ?: throw BitrixException::cannotSync('Bitrix24 did not return the new task.');

        $this->applyStatus($actor, $task, (int) ($created['status'] ?? self::PENDING));
        $task->bitrix_synced_at = now();
        BitrixOutboundOp::record(BitrixOutboundOp::TASK, $task->bitrix_task_id, 'create', BitrixFingerprint::task($task), $actor);
    }

    /**
     * Sends the task's unsaved changes (its dirty attributes).
     */
    public function update(User $actor, Task $task): void
    {
        if (! $task->bitrix_task_id || ! $this->syncs($task)) {
            return;
        }

        $this->ensureLinked($actor);

        $changed = array_keys(array_intersect_key($task->getDirty(), self::FIELDS));
        if ($changed !== []) {
            $this->bitrix->callAsUser($actor, 'tasks.task.update', [
                'taskId' => $task->bitrix_task_id,
                'fields' => $this->fields($task, $changed),
            ]);
        }

        if ($task->isDirty('status')) {
            $this->applyStatus($actor, $task, $this->bitrixStatus($task->getOriginal('status')));
        }

        if ($changed !== [] || $task->isDirty('status')) {
            $task->bitrix_synced_at = now();
            BitrixOutboundOp::record(BitrixOutboundOp::TASK, $task->bitrix_task_id, 'update', BitrixFingerprint::task($task), $actor);
        }
    }

    public function delete(User $actor, Task $task): void
    {
        if ($task->bitrix_task_id && $this->syncs($task)) {
            $this->ensureLinked($actor);
            $this->bitrix->callAsUser($actor, 'tasks.task.delete', ['taskId' => $task->bitrix_task_id]);
            BitrixOutboundOp::record(BitrixOutboundOp::TASK, $task->bitrix_task_id, 'delete', null, $actor);
        }
    }

    /**
     * Checked first, so an employee who never signed in with Bitrix gets
     * that reason rather than an error about the fields they sent.
     */
    private function ensureLinked(User $actor): void
    {
        if (! $actor->isLinkedToBitrix()) {
            throw BitrixException::missingToken();
        }
    }

    /**
     * Status is changed through actions, never by writing STATUS. Each action
     * returns the task, so we walk from the status Bitrix reports until we
     * reach the target or Bitrix stops us (e.g. completing a task that needs
     * the creator's approval ends in "awaiting control"). Bitrix's final
     * status is what we keep.
     */
    private function applyStatus(User $actor, Task $task, int $current): void
    {
        $target = $this->bitrixStatus($task->status);

        for ($step = 0; $step < 3 && ($action = $this->nextAction($current, $target)); $step++) {
            $result = $this->bitrix->callAsUser($actor, "tasks.task.{$action}", ['taskId' => $task->bitrix_task_id]);
            $current = (int) ($result['result']['task']['status'] ?? $current);

            // Completing landed in the creator's review: never approve it in the same go.
            if ($current === self::AWAITING_CONTROL) {
                break;
            }
        }

        // A custom status of ours that sends the same Bitrix value stays as chosen.
        if ($this->normalize($current) !== $target) {
            $task->status = TaskStatus::keyForBitrix($current) ?? $task->status;
        }
    }

    private function nextAction(int $current, int $target): ?string
    {
        $current = $this->normalize($current);

        if ($current === $target) {
            return null;
        }

        return match (true) {
            $current === self::AWAITING_CONTROL => $target === self::COMPLETED ? 'approve' : 'disapprove',
            in_array($current, [self::COMPLETED, self::DECLINED], true) => 'renew',
            $target === self::PENDING => $current === self::IN_PROGRESS ? 'pause' : 'renew',
            $target === self::IN_PROGRESS => 'start',
            in_array($target, [self::AWAITING_CONTROL, self::COMPLETED], true) => 'complete',
            $target === self::DEFERRED => 'defer',
            default => null,
        };
    }

    private function normalize(int $status): int
    {
        return $status === self::NEW ? self::PENDING : $status;
    }

    private function bitrixStatus(?string $key): int
    {
        $bitrixId = TaskStatus::where('key', $key)->value('bitrix_id');

        return $this->normalize((int) ($bitrixId ?? throw BitrixException::cannotSync("Status \"{$key}\" has no Bitrix24 value.")));
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    private function fields(Task $task, array $columns): array
    {
        $fields = [];

        foreach ($columns as $column) {
            $fields[self::FIELDS[$column]] = match ($column) {
                'title' => $task->title,
                'description' => $task->description ?? '',
                'priority' => TaskPriority::where('key', $task->priority)->value('bitrix_id')
                    ?? throw BitrixException::cannotSync("Priority \"{$task->priority}\" has no Bitrix24 value."),
                // An empty value clears the deadline.
                'due_date' => $task->due_date?->toIso8601String() ?? '',
                'assigned_to' => $this->responsibleId($task->assigned_to),
            };
        }

        return $fields;
    }

    private function responsibleId(?int $userId): int
    {
        if ($userId === null) {
            throw BitrixException::cannotSync('A Bitrix24 task always has a responsible person; choose an assignee.');
        }

        $user = User::find($userId);

        return $user?->bitrix_user_id
            ?? throw BitrixException::cannotSync(($user->name ?? 'This user').' is not a Bitrix24 user, so the task cannot be assigned to them.');
    }
}
