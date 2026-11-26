<?php

declare(strict_types=1);

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Services\ActivityLogService;

beforeEach(function (): void {
    $this->activities = Mockery::mock(ActivityLogRepositoryInterface::class);
    $this->service = new ActivityLogService($this->activities);
    $this->written = null;
    $this->activities->shouldReceive('create')->once()->andReturnUsing(function (array $attributes): ActivityLog {
        $this->written = $attributes;

        return new ActivityLog;
    });
    $this->actor = (new User)->forceFill(['id' => 4]);
    $this->task = (new Task)->forceFill(['id' => 9, 'title' => 'Ship', 'project_id' => 3, 'created_by' => 7]);
    $this->project = (new Project)->forceFill(['id' => 3, 'title' => 'Launch', 'owner_id' => 8]);
});

it('logs a new task under the signed-in user', function (): void {
    $this->service->logTaskCreated($this->task, $this->actor);

    expect($this->written)->toBe([
        'user_id' => 4,
        'project_id' => 3,
        'action' => ActivityAction::TaskCreated,
        'description' => "Task 'Ship' was created.",
        'metadata' => ['task_id' => 9, 'title' => 'Ship'],
    ]);
});

it('logs a task change without a signed-in user under its creator, with what changed', function (): void {
    $this->task->syncOriginal();
    $this->task->forceFill(['title' => 'Ship it'])->syncChanges();

    $this->service->logTaskUpdated($this->task, null);

    expect($this->written)
        ->user_id->toBe(7)
        ->action->toBe(ActivityAction::TaskUpdated)
        ->description->toBe("Task 'Ship it' was updated.")
        ->metadata->toBe(['title' => 'Ship it']);
});

it('logs a deleted task', function (): void {
    $this->service->logTaskDeleted($this->task, $this->actor);

    expect($this->written)->action->toBe(ActivityAction::TaskDeleted)->description->toBe("Task 'Ship' was deleted.");
});

it('logs a new project under its owner when nobody is signed in', function (): void {
    $this->service->logProjectCreated($this->project, null);

    expect($this->written)->toBe([
        'user_id' => 8,
        'project_id' => 3,
        'action' => ActivityAction::ProjectCreated,
        'description' => "Project 'Launch' was created.",
        'metadata' => ['title' => 'Launch'],
    ]);
});

it('logs a project change with what changed', function (): void {
    $this->project->syncOriginal();
    $this->project->forceFill(['title' => 'Launch 2'])->syncChanges();

    $this->service->logProjectUpdated($this->project, $this->actor);

    expect($this->written)->action->toBe(ActivityAction::ProjectUpdated)->metadata->toBe(['title' => 'Launch 2']);
});

it('logs a deleted project without metadata, as before', function (): void {
    $this->service->logProjectDeleted($this->project, $this->actor);

    expect($this->written)->toBe([
        'user_id' => 4,
        'project_id' => 3,
        'action' => ActivityAction::ProjectDeleted,
        'description' => "Project 'Launch' was deleted.",
    ]);
});

it('logs a member added to a project by name, under who added them', function (): void {
    $member = (new User)->forceFill(['id' => 16, 'name' => 'Abdulkarim Wanly']);

    $this->service->logMemberAdded($this->actor, $this->project, $member);

    expect($this->written)->toBe([
        'user_id' => 4,
        'project_id' => 3,
        'action' => ActivityAction::MemberAdded,
        'description' => "Abdulkarim Wanly was added to project 'Launch'.",
    ]);
});

it('logs a member removed from a project by name', function (): void {
    $member = (new User)->forceFill(['id' => 16, 'name' => 'Abdulkarim Wanly']);

    $this->service->logMemberRemoved($this->actor, $this->project, $member);

    expect($this->written)->action->toBe(ActivityAction::MemberRemoved)
        ->description->toBe("Abdulkarim Wanly was removed from project 'Launch'.");
});
