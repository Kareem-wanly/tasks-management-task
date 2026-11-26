<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/*
 * C.1, second half: users, roles, permissions, statuses and priorities. The
 * wrappers and every field the frontend reads stay as they were; internal
 * columns (Bitrix token expiry, departments, pivot rows...) no longer leave
 * the server.
 */

beforeEach(function (): void {
    $this->seed();
    $this->admin = User::whereHas('roles', fn ($q) => $q->where('name', 'Administrator'))->firstOrFail();
    Sanctum::actingAs($this->admin);
    $this->role = Role::where('name', '!=', 'Administrator')->firstOrFail();
    $this->user = User::factory()->create();
    $this->user->roles()->attach($this->role->id);
});

const HIDDEN_USER_FIELDS = ['bitrix_token_expires_at', 'bitrix_department_ids', 'email_verified_at', 'bitrix_synced_at', 'bitrix_user_id', 'password', 'remember_token'];

function expectPlainUser(array $user): void
{
    expect($user)->not->toHaveKeys(HIDDEN_USER_FIELDS);
}

function expectRoleRefs(array $roles): void
{
    expect($roles)->not->toBeEmpty();
    foreach ($roles as $role) {
        expect(array_keys($role))->toBe(['id', 'name', 'display_name']);
    }
}

it('pages the users with their roles, paging info under meta', function (): void {
    $response = $this->getJson('/api/v1/users')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'email', 'is_active', 'created_at', 'roles' => [['id', 'name', 'display_name']]]],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

    $listed = collect($response->json('data'))->firstWhere('id', $this->user->id);
    expectPlainUser($listed);
    expectRoleRefs($listed['roles']);
});

it('answers a user read or change with the user', function (string $method, string $uri, array $body, ?string $message): void {
    $response = $this->json($method, str_replace('{user}', (string) $this->user->id, $uri), $body)
        ->assertOk()
        ->assertJsonPath('data.id', $this->user->id)
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'is_active', 'created_at']]);

    if ($message !== null) {
        $response->assertJsonPath('message', $message);
    }
    expectPlainUser($response->json('data'));
})->with([
    'show' => ['GET', '/api/v1/users/{user}', [], null],
    'update' => ['PUT', '/api/v1/users/{user}', ['name' => 'Renamed'], 'User details updated successfully'],
    'deactivate' => ['PATCH', '/api/v1/users/{user}/active', ['active' => false], 'User deactivated'],
]);

it('lists user options as id, name and email', function (): void {
    $response = $this->getJson('/api/v1/users/options')->assertOk();

    expect(collect($response->json('data'))->firstWhere('id', $this->user->id))
        ->toBe(['id' => $this->user->id, 'name' => $this->user->name, 'email' => $this->user->email]);
});

it('lists a user\'s roles and answers a role change with the user', function (): void {
    $this->getJson("/api/v1/users/{$this->user->id}/roles")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'User roles retrieved successfully')
        ->assertJson(fn ($json) => $json->has('data', 1)->etc());
    expectRoleRefs($this->getJson("/api/v1/users/{$this->user->id}/roles")->json('data'));

    $response = $this->putJson("/api/v1/users/{$this->user->id}/roles", ['roles' => [$this->role->id]])
        ->assertOk()
        ->assertJsonPath('message', 'User roles updated successfully')
        ->assertJsonPath('user.id', $this->user->id);
    expectPlainUser($response->json('user'));
    expectRoleRefs($response->json('user.roles'));
});

it('lists the roles with their permissions and counts', function (): void {
    $response = $this->getJson('/api/v1/roles')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'display_name', 'description', 'permissions_count', 'users_count', 'permissions' => [['id', 'name', 'display_name', 'description']]]]]);

    expect($response->json('data.0.permissions.0'))->not->toHaveKey('pivot');
});

it('answers a role read or change with the role and its permissions', function (string $method, string $uri, array $body, string $key, ?string $message): void {
    $response = $this->json($method, str_replace('{role}', (string) $this->role->id, $uri), $body)
        ->assertSuccessful()
        ->assertJsonStructure([$key => ['id', 'name', 'display_name', 'description', 'permissions' => [['id', 'name', 'display_name', 'description']]]]);

    if ($message !== null) {
        $response->assertJsonPath('message', $message);
    }
    expect($response->json("{$key}.permissions.0"))->not->toHaveKey('pivot');
})->with([
    'show' => ['GET', '/api/v1/roles/{role}', [], 'role', null],
    'create' => ['POST', '/api/v1/roles', ['name' => 'auditor_x', 'display_name' => 'Auditor X', 'description' => null, 'permissions' => [1]], 'data', 'Role created successfully'],
    'update' => ['PUT', '/api/v1/roles/{role}', ['display_name' => 'Renamed role'], 'data', 'Role updated successfully'],
    'permissions' => ['PUT', '/api/v1/roles/{role}/permissions', ['permissions' => [1]], 'data', 'Role permissions synchronized successfully'],
]);

it('lists the permissions', function (): void {
    $this->getJson('/api/v1/permissions')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'display_name', 'description']]]);
});

it('answers task statuses and priorities with exactly their fields', function (string $uri, array $fields, array $create, string $model): void {
    $listed = $this->getJson($uri)->assertOk()->json('data.0');
    expect(array_keys($listed))->toBe($fields);

    $created = $this->postJson($uri, $create)->assertCreated()->json('data');
    expect(array_keys($created))->toBe($fields);

    $lookup = $model::where('is_system', false)->firstOrFail();
    $updated = $this->putJson("{$uri}/{$lookup->id}", ['name' => 'Renamed', 'color' => 'info'])->assertOk()->json('data');
    expect(array_keys($updated))->toBe($fields)->and($updated['name'])->toBe('Renamed');
})->with([
    'statuses' => ['/api/v1/task-statuses', ['id', 'key', 'name', 'bitrix_id', 'is_system', 'is_final', 'sort', 'color'], ['key' => 'review_x', 'name' => 'Review X', 'bitrix_id' => 3, 'is_final' => false, 'color' => 'info'], TaskStatus::class],
    'priorities' => ['/api/v1/task-priorities', ['id', 'key', 'name', 'bitrix_id', 'is_system', 'sort', 'color'], ['key' => 'urgent_x', 'name' => 'Urgent X', 'bitrix_id' => 2, 'color' => 'danger'], TaskPriority::class],
]);

it('answers a profile change with the user, nothing internal', function (): void {
    $response = $this->putJson('/api/v1/me', ['name' => 'Me Renamed', 'email' => $this->admin->email])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Profile updated successfully')
        ->assertJsonPath('data.name', 'Me Renamed');

    expectPlainUser($response->json('data'));
});
