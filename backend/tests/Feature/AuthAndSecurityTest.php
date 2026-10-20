<?php

namespace Tests\Feature;

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
            'name'                  => 'Test User',
            'email'                 => 'newuser@example.com',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['access_token', 'user']);

        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'email'    => 'admin@example.com',
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

        $targetUser = User::factory()->create();
        $targetUser->roles()->attach($memberRole);

        $response = $this->actingAs($normalUser)
                         ->putJson("/api/users/{$targetUser->id}/roles", [
                             'roles' => [$adminRole->id],
                         ]);

        $response->assertStatus(403);
    }
}