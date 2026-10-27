<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Round3ReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function deleter(): User
    {
        $role = Role::create(['name' => 'del_'.uniqid(), 'display_name' => 'D', 'description' => 'x']);
        $role->permissions()->sync(Permission::whereIn('name', ['users.view', 'users.delete'])->pluck('id'));
        $u = User::factory()->create();
        $u->roles()->attach($role);

        return $u;
    }

    public function test_f1_non_admin_cannot_delete_an_administrator(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'Administrator')->first());
        Sanctum::actingAs($this->deleter());
        $this->deleteJson("/api/users/{$admin->id}")->assertStatus(403);
    }

    public function test_f1_user_cannot_delete_own_account(): void
    {
        $me = $this->deleter();
        Sanctum::actingAs($me);
        $this->deleteJson("/api/users/{$me->id}")->assertStatus(403);
    }

    public function test_f3_project_delete_logs_task_deleted_activity(): void
    {
        $owner = User::factory()->create();
        $owner->roles()->attach(Role::where('name', 'Member')->first());
        $p = Project::forceCreate(['title' => 'P', 'status' => 'active', 'owner_id' => $owner->id]);
        Task::forceCreate(['title' => 'T', 'status' => 'todo', 'priority' => 'low', 'project_id' => $p->id, 'created_by' => $owner->id]);
        Sanctum::actingAs($owner);
        $before = ActivityLog::where('action', 'task_deleted')->count();
        $p->delete();
        $this->assertGreaterThan($before, ActivityLog::where('action', 'task_deleted')->count());
    }

    public function test_f3_restore_does_not_revive_tasks_deleted_earlier(): void
    {
        $owner = User::factory()->create();
        $p = Project::forceCreate(['title' => 'P', 'status' => 'active', 'owner_id' => $owner->id]);
        $old = Task::forceCreate(['title' => 'old', 'status' => 'todo', 'priority' => 'low', 'project_id' => $p->id, 'created_by' => $owner->id]);
        $keep = Task::forceCreate(['title' => 'keep', 'status' => 'todo', 'priority' => 'low', 'project_id' => $p->id, 'created_by' => $owner->id]);
        $old->delete();                 // deleted on purpose, before the project
        $this->travel(1)->minutes();
        $p->delete();
        $p->restore();
        $this->assertNull(Task::find($keep->id)?->deleted_at, 'keep should be back');
        $this->assertNotNull(Task::withTrashed()->find($old->id)->deleted_at, 'old task should stay deleted');
    }

    public function test_f2_rename_migration_merges_old_permission_without_duplicates(): void
    {
        $role = Role::where('name', 'Administrator')->first();
        $new = Permission::where('name', 'users.manage_roles')->first();
        $oldId = \DB::table('permissions')->insertGetId([
            'name' => 'users.manage-roles', 'description' => 'old', 'created_at' => now(), 'updated_at' => now(),
        ]);
        \DB::table('permission_role')->insert(['role_id' => $role->id, 'permission_id' => $oldId]);
        $role->permissions()->syncWithoutDetaching([$new->id]);

        (require database_path('migrations/2026_10_25_120000_rename_manage_roles_permission.php'))->up();

        $this->assertDatabaseMissing('permissions', ['name' => 'users.manage-roles']);
        $this->assertSame(1, \DB::table('permission_role')
            ->where('role_id', $role->id)->where('permission_id', $new->id)->count());
    }
}
