<?php

declare(strict_types=1);

use App\Actions\Bitrix\SyncBitrixTaskAction;
use App\Integrations\Bitrix24\Contracts\BitrixTaskMapperInterface;
use App\Integrations\Bitrix24\Contracts\BitrixTaskResolverInterface;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Repositories\Contracts\TaskLookupRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;

beforeEach(function (): void {
    $this->resolver = Mockery::mock(BitrixTaskResolverInterface::class);
    $this->mapper = Mockery::mock(BitrixTaskMapperInterface::class);
    $this->tasks = Mockery::mock(TaskRepositoryInterface::class);
    $this->lookups = Mockery::mock(TaskLookupRepositoryInterface::class);
    $this->action = new SyncBitrixTaskAction($this->resolver, $this->mapper, $this->tasks, Mockery::mock(UserRepositoryInterface::class), $this->lookups);
    $this->resolved = [
        'bitrix_task_id' => 15, 'title' => 'Ship', 'description' => null, 'status' => 'in_progress', 'priority' => 'normal',
        'due_date' => null, 'allow_time_tracking' => false, 'time_estimate' => 0, 'time_spent' => 0, 'completed_at' => null,
        'project_id' => null, 'parent_id' => null, 'created_by' => 1, 'assigned_to' => null,
    ];
});

it('skips a task the resolver cannot place (creator or project not imported)', function (): void {
    $this->resolver->shouldReceive('resolve')->andReturnNull();
    $this->tasks->shouldNotReceive('save');

    expect($this->action->handle(['id' => 15]))->toBeNull();
});

it('keeps our custom status while Bitrix still sends the value it maps to', function (): void {
    $task = (new Task)->forceFill([...$this->resolved, 'id' => 9, 'status' => 'urgent_review', 'deleted_at' => null]);
    $task->syncOriginal();
    $task->exists = true;
    $this->resolver->shouldReceive('resolve')->andReturn($this->resolved);
    $this->tasks->shouldReceive('findByBitrixId')->with(15, true)->andReturn($task);
    // Both keys send Bitrix status 3 (in progress); the priority is unchanged.
    $this->lookups->shouldReceive('bitrixIdForKey')->with(TaskStatus::class, 'urgent_review')->andReturn(3);
    $this->lookups->shouldReceive('bitrixIdForKey')->with(TaskStatus::class, 'in_progress')->andReturn(3);
    $this->lookups->shouldReceive('bitrixIdForKey')->with(TaskPriority::class, Mockery::any())->never();
    $this->mapper->shouldReceive('members')->andReturnNull();
    $this->tasks->shouldNotReceive('save');
    $this->tasks->shouldReceive('markSynced')->once()->with($task);

    expect($this->action->handle(['id' => 15]))->toBe($task)
        ->and($task->status)->toBe('urgent_review');
});
