<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_can_register_and_receives_token(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Test User',
            'email' => 'newuser@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['access_token', 'user']);

        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'admin@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['access_token']);
    }

    public function test_non_admin_cannot_assign_administrator_role(): void
    {
        $adminRole = Role::where('name', 'Administrator')->first();
        $memberRole = Role::where('name', 'Member')->first();

        /** @var User $normalUser */
        $normalUser = User::factory()->create();
        $normalUser->roles()->attach($memberRole);

        /** @var User $targetUser */
        $targetUser = User::factory()->create();
        $targetUser->roles()->attach($memberRole);

        $response = $this->actingAs($normalUser)
            ->putJson("/api/v1/users/{$targetUser->id}/roles", [
                'roles' => [$adminRole->id],
            ]);

        $response->assertStatus(403);
    }

    public function test_user_cannot_modify_their_own_roles(): void
    {
        $memberRole = Role::where('name', 'Member')->first();

        /** @var User $user */
        $user = User::factory()->create();
        $user->roles()->attach($memberRole);

        $response = $this->actingAs($user)
            ->putJson("/api/v1/users/{$user->id}/roles", [
                'roles' => [$memberRole->id],
            ]);

        $response->assertStatus(403);
    }

    public function test_task_cannot_be_assigned_to_non_project_member(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();
        // May create and assign tasks, so the request reaches validation.
        $owner->roles()->attach(Role::where('name', 'Project Manager')->first());
        $outsider = User::factory()->create();

        $project = Project::create([
            'title' => 'Test Project',
            'owner_id' => $owner->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($owner)
            ->postJson("/api/v1/projects/{$project->id}/tasks", [
                'title' => 'Test Task',
                'assigned_to' => $outsider->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['assigned_to']);
    }

    /** SEC-2: a project member who was deactivated still has the membership row. */
    public function test_task_cannot_be_assigned_to_a_deactivated_project_member(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();
        $owner->roles()->attach(Role::where('name', 'Project Manager')->first());
        $deactivated = User::factory()->create(['is_active' => false]);

        $project = Project::create(['title' => 'Test Project', 'owner_id' => $owner->id, 'status' => 'active']);
        $project->members()->attach($deactivated->id, ['role' => 'member']);

        $this->actingAs($owner)
            ->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Test Task', 'assigned_to' => $deactivated->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['assigned_to']);
    }

    public function test_project_owner_can_be_deactivated(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $owner = User::factory()->create();

        Project::create([
            'title' => 'Owned Project',
            'owner_id' => $owner->id,
            'status' => 'active',
        ]);

        // Deactivating (which replaced deleting) keeps their projects intact.
        $this->actingAs($admin)
            ->patchJson("/api/v1/users/{$owner->id}/active", ['active' => false])
            ->assertOk();

        $this->assertFalse($owner->fresh()->is_active);
        $this->assertSame(1, Project::where('owner_id', $owner->id)->count());
    }

    public function test_options_endpoints_return_data_array(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/users/options');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }
}
