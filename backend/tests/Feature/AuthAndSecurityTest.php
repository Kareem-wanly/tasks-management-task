<?php

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
        $response = $this->postJson('/api/register', [
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
        $response = $this->postJson('/api/login', [
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
            ->putJson("/api/users/{$targetUser->id}/roles", [
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
            ->putJson("/api/users/{$user->id}/roles", [
                'roles' => [$memberRole->id],
            ]);

        $response->assertStatus(403);
    }

    public function test_task_cannot_be_assigned_to_non_project_member(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();
        $outsider = User::factory()->create();

        $project = Project::create([
            'title' => 'Test Project',
            'owner_id' => $owner->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($owner)
            ->postJson("/api/projects/{$project->id}/tasks", [
                'title' => 'Test Task',
                'assigned_to' => $outsider->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['assigned_to']);
    }

    public function test_deleting_project_owner_returns_409(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $owner = User::factory()->create();

        Project::create([
            'title' => 'Owned Project',
            'owner_id' => $owner->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/users/{$owner->id}");

        $response->assertStatus(409);
    }

    public function test_options_endpoints_return_data_array(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        $response = $this->actingAs($admin)
            ->getJson('/api/users/options');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }
}
