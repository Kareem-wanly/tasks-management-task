<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->project = Project::forceCreate(['title' => 'P', 'status' => 'active', 'owner_id' => $this->owner->id]);
});

function saveTask(array $attributes): Task
{
    $task = (new Task)->forceFill($attributes + ['title' => 'T', 'status' => 'todo', 'priority' => 'medium']);
    resolve(TaskRepositoryInterface::class)->save($task);

    return $task;
}

it('dates a task when it closes, keeps the date while closed and clears it when it reopens', function (): void {
    $tasks = resolve(TaskRepositoryInterface::class);
    $task = saveTask(['project_id' => $this->project->id, 'created_by' => $this->owner->id]);
    expect($task->completed_at)->toBeNull();

    $task->status = 'completed';
    $tasks->save($task);
    $closedAt = $task->completed_at;
    expect($closedAt)->not->toBeNull();

    $this->travel(1)->hours();
    $task->title = 'Renamed';
    $tasks->save($task);
    expect($task->completed_at?->equalTo($closedAt))->toBeTrue();

    $task->status = 'in_progress';
    $tasks->save($task);
    expect($task->completed_at)->toBeNull();
});

it('flags overdue tasks in a list, and not closed ones', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::where('name', 'Administrator')->first());
    $late = saveTask(['project_id' => $this->project->id, 'created_by' => $this->owner->id, 'due_date' => now()->subDay()]);
    $done = saveTask(['project_id' => $this->project->id, 'created_by' => $this->owner->id, 'due_date' => now()->subDay(), 'status' => 'completed']);
    Sanctum::actingAs($admin);

    $flags = collect($this->getJson('/api/v1/tasks')->assertOk()->json('data'))->pluck('is_overdue', 'id');

    expect($flags[$late->id])->toBeTrue()
        ->and($flags[$done->id])->toBeFalse();
});

it('deletes a project gone from Bitrix with its tasks', function (): void {
    $this->project->forceFill(['bitrix_group_id' => 42])->save();
    $task = saveTask(['project_id' => $this->project->id, 'created_by' => $this->owner->id]);

    expect(resolve(ProjectRepositoryInterface::class)->deleteByBitrixIds([42]))->toBe(1);

    $this->assertSoftDeleted($this->project);
    $this->assertSoftDeleted($task);
});
