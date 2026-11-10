<?php

namespace Tests\Feature;

use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\Contracts\HasBitrixToken;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskLookupsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function manager(): User
    {
        $role = Role::create(['name' => 'settings_'.uniqid(), 'display_name' => 'S', 'description' => 'x']);
        $role->permissions()->sync(Permission::where('name', 'task_settings.manage')->pluck('id'));
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'Member')->first());

        return $user;
    }

    private function task(array $attributes = []): Task
    {
        $owner = User::factory()->create();
        $attributes['project_id'] ??= Project::forceCreate(['title' => 'P', 'status' => 'active', 'owner_id' => $owner->id])->id;

        return Task::forceCreate($attributes + [
            'title' => 'T', 'status' => 'todo', 'priority' => 'medium', 'created_by' => $owner->id,
        ]);
    }

    public function test_migration_seeds_the_bitrix_defaults(): void
    {
        $this->assertSame(
            ['todo' => 2, 'in_progress' => 3, 'review' => 4, 'completed' => 5, 'deferred' => 6],
            TaskStatus::ordered()->pluck('bitrix_id', 'key')->all()
        );
        $this->assertSame(['completed'], TaskStatus::finalKeys());
        $this->assertSame(
            ['low' => 0, 'medium' => 1, 'high' => 2, 'urgent' => 2],
            TaskPriority::ordered()->pluck('bitrix_id', 'key')->all()
        );
        $this->assertFalse(TaskPriority::where('key', 'urgent')->first()->is_system);
    }

    public function test_any_user_can_list_lookups_in_order(): void
    {
        Sanctum::actingAs($this->member());

        $this->getJson('/api/task-statuses')->assertOk()
            ->assertJsonPath('data.0.key', 'todo')
            ->assertJsonPath('data.3.is_final', true)
            ->assertJsonMissingPath('data.0.created_at');
        $this->getJson('/api/task-priorities')->assertOk()->assertJsonCount(4, 'data');
    }

    public function test_managing_lookups_needs_the_permission(): void
    {
        Sanctum::actingAs($this->member());

        $this->postJson('/api/task-statuses', ['key' => 'blocked', 'name' => 'Blocked'])->assertForbidden();
        $this->putJson('/api/task-priorities/1', ['name' => 'X'])->assertForbidden();
        $this->deleteJson('/api/task-priorities/4')->assertForbidden();
    }

    public function test_custom_final_status_closes_tasks_like_completed(): void
    {
        Sanctum::actingAs($this->manager());

        $this->postJson('/api/task-statuses', [
            'key' => 'cancelled', 'name' => 'Cancelled', 'is_final' => true, 'color' => 'danger', 'bitrix_id' => 5,
        ])->assertCreated()->assertJsonPath('data.sort', 6)->assertJsonPath('data.is_system', false);

        $task = $this->task(['status' => 'cancelled', 'due_date' => now()->subDay()]);

        $this->assertNotNull($task->completed_at);
        $this->assertFalse($task->is_overdue);
        $this->assertFalse(Task::overdue()->whereKey($task->id)->exists());

        Sanctum::actingAs(User::where('email', 'admin@example.com')->first());
        $this->getJson("/api/projects/{$task->project_id}")->assertJsonPath('data.completed_tasks_count', 1);
    }

    public function test_lookup_input_is_validated(): void
    {
        Sanctum::actingAs($this->manager());

        $this->postJson('/api/task-statuses', ['key' => 'todo', 'name' => 'Dup'])->assertJsonValidationErrors('key');
        $this->postJson('/api/task-statuses', ['key' => 'Bad Key', 'name' => 'X'])->assertJsonValidationErrors('key');
        $this->postJson('/api/task-statuses', ['key' => 'x', 'name' => 'X', 'color' => '#fff'])->assertJsonValidationErrors('color');
        // A custom value can only be sent as one of Bitrix's own values
        $this->postJson('/api/task-priorities', ['key' => 'x', 'name' => 'X', 'bitrix_id' => 9])->assertJsonValidationErrors('bitrix_id');
    }

    public function test_bitrix_values_keep_their_link_but_can_be_renamed(): void
    {
        Sanctum::actingAs($this->manager());
        $completed = TaskStatus::where('key', 'completed')->first();

        $this->putJson("/api/task-statuses/{$completed->id}", [
            'name' => 'Done', 'is_final' => false, 'bitrix_id' => 2, 'key' => 'done',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Done')
            ->assertJsonPath('data.is_final', true)
            ->assertJsonPath('data.bitrix_id', 5)
            ->assertJsonPath('data.key', 'completed');
    }

    public function test_only_unused_custom_values_can_be_deleted(): void
    {
        Sanctum::actingAs($this->manager());
        $high = TaskPriority::where('key', 'high')->first();
        $this->deleteJson("/api/task-priorities/{$high->id}")->assertStatus(422);

        $id = $this->postJson('/api/task-priorities', ['key' => 'someday', 'name' => 'Someday'])->json('data.id');
        $task = $this->task(['priority' => 'someday']);
        $task->delete(); // soft-deleted tasks still reference it
        $this->deleteJson("/api/task-priorities/{$id}")->assertStatus(422);

        $task->forceDelete();
        $this->deleteJson("/api/task-priorities/{$id}")->assertOk();
        $this->assertDatabaseMissing('task_priorities', ['key' => 'someday']);
    }

    public function test_tasks_only_accept_known_statuses_and_priorities(): void
    {
        $task = $this->task();
        Sanctum::actingAs(User::where('email', 'admin@example.com')->first());

        $this->putJson("/api/tasks/{$task->id}", ['status' => 'nope', 'priority' => 'nope'])
            ->assertJsonValidationErrors(['status', 'priority']);
        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'deferred'])
            ->assertOk()->assertJsonPath('data.status', 'deferred');

        $this->expectException(QueryException::class);
        Task::whereKey($task->id)->update(['status' => 'nope']);
    }

    public function test_finished_filter_counts_every_closing_status(): void
    {
        Sanctum::actingAs($this->manager());
        $this->postJson('/api/task-statuses', ['key' => 'cancelled', 'name' => 'Cancelled', 'is_final' => true])->assertCreated();
        $projectId = $this->task(['status' => 'completed'])->project_id;
        $this->task(['status' => 'cancelled', 'project_id' => $projectId]);
        $this->task(['status' => 'deferred', 'project_id' => $projectId]);
        Sanctum::actingAs(User::where('email', 'admin@example.com')->first());

        $this->getJson("/api/tasks?project_id={$projectId}&finished=true")->assertJsonPath('meta.total', 2);
        $this->getJson("/api/tasks?project_id={$projectId}&finished=false")->assertJsonPath('meta.total', 1);
    }

    public function test_sorting_by_priority_uses_the_lookup_order(): void
    {
        $projectId = $this->task(['priority' => 'medium'])->project_id;
        foreach (['urgent', 'low', 'high'] as $priority) {
            $this->task(['priority' => $priority, 'project_id' => $projectId]);
        }
        Sanctum::actingAs(User::where('email', 'admin@example.com')->first());

        $keys = collect($this->getJson("/api/tasks?project_id={$projectId}&sort_by=priority&sort_order=asc")->json('data'))
            ->pluck('priority')->unique()->values()->all();

        $this->assertSame(['low', 'medium', 'high', 'urgent'], $keys);
    }

    public function test_sync_command_renames_bitrix_values_and_adds_new_ones(): void
    {
        $this->fakeServiceAccount();
        Http::fake(['https://portal.test/rest/tasks.task.getFields.json' => Http::response(['result' => ['fields' => [
            'STATUS' => ['type' => 'enum', 'values' => ['2' => 'Pending', '3' => 'In Progress', '4' => 'Awaiting Review', '5' => 'Completed', '6' => 'Deferred', '7' => 'Supposedly Completed']],
            'PRIORITY' => ['type' => 'enum', 'values' => ['2' => 'High', '1' => 'Medium', '0' => 'Low']],
        ]]])]);

        $this->artisan('bitrix:sync-task-lookups')->assertSuccessful();

        $this->assertSame('Pending', TaskStatus::where('key', 'todo')->value('name'));
        $this->assertSame('Awaiting Review', TaskStatus::where('key', 'review')->value('name'));
        $added = TaskStatus::where('bitrix_id', 7)->first();
        $this->assertSame('bitrix_status_7', $added->key);
        $this->assertTrue($added->is_system);
        $this->assertSame(6, $added->sort);
        // Our own "urgent" shares Bitrix ID 2 with "high" but is not renamed
        $this->assertSame('Urgent', TaskPriority::where('key', 'urgent')->value('name'));
        $this->assertSame(4, TaskPriority::count());

        // Running it again changes nothing
        $this->artisan('bitrix:sync-task-lookups')->assertSuccessful();
        $this->assertSame(6, TaskStatus::count());
    }

    public function test_sync_command_fails_cleanly_when_bitrix_errors(): void
    {
        $this->fakeServiceAccount();
        Sleep::fake();
        Http::fake(['https://portal.test/rest/*' => Http::response(['error' => 'insufficient_scope', 'error_description' => 'Missing scope'], 401)]);

        $this->artisan('bitrix:sync-task-lookups')->assertFailed();
        $this->assertSame('To Do', TaskStatus::where('key', 'todo')->value('name'));
    }

    private function fakeServiceAccount(): void
    {
        config(['bitrix.portal' => 'portal.test']);
        $this->app->instance(BitrixClient::SERVICE_ACCOUNT, new class implements HasBitrixToken
        {
            public function bitrixToken(): ?BitrixToken
            {
                return new BitrixToken('access', 'refresh', CarbonImmutable::now()->addHour());
            }

            public function storeBitrixToken(BitrixToken $token): void {}

            public function bitrixTokenLockKey(): string
            {
                return 'bitrix-token:test';
            }
        });
    }
}
