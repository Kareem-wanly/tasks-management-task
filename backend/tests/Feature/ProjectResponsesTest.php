<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/*
 * C.1: the project, member, activity and project-task responses. The
 * wrapper and every field the frontend reads stay as they were; internal
 * columns (Bitrix token expiry, departments, pivot rows...) no longer leave
 * the server.
 */

beforeEach(function (): void {
    $this->seed();
    $this->admin = User::whereHas('roles', fn ($q) => $q->where('name', 'Administrator'))->firstOrFail();
    Sanctum::actingAs($this->admin);
    $this->member = User::factory()->create();
    $this->project = Project::factory()->create(['owner_id' => $this->admin->id, 'bitrix_group_id' => null]);
    $this->project->members()->attach($this->member->id, ['role' => 'member']);
    Task::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->admin->id, 'assigned_to' => $this->member->id]);
});

/** Columns that must never reach the browser. */
const INTERNAL_USER_FIELDS = ['bitrix_token_expires_at', 'bitrix_department_ids', 'email_verified_at', 'bitrix_synced_at', 'pivot'];

function expectNoInternalUserFields(array $user): void
{
    foreach (INTERNAL_USER_FIELDS as $field) {
        expect($user)->not->toHaveKey($field);
    }
}

it('lists project members with their role', function (): void {
    $response = $this->getJson("/api/v1/projects/{$this->project->id}/members")
        ->assertOk()
        ->assertJsonStructure(['success', 'message', 'data' => [['id', 'name', 'email']]]);

    $member = $response->json('data.0');
    expect($member)->toBe(['id' => $this->member->id, 'name' => $this->member->name, 'email' => $this->member->email, 'role' => 'member']);
});

it('answers member and archive changes with the project', function (string $method, string $uri, array $body, string $message): void {
    $other = User::factory()->create();
    $uri = str_replace(['{project}', '{user}'], [(string) $this->project->id, (string) $this->member->id], $uri);
    $body = array_map(fn ($value) => $value === '{other}' ? $other->id : $value, $body);

    $response = $this->json($method, $uri, $body)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', $message)
        ->assertJsonPath('data.id', $this->project->id)
        ->assertJsonStructure(['data' => ['id', 'title', 'description', 'status', 'start_date', 'due_date', 'owner' => ['id', 'name', 'email'], 'members', 'created_at', 'updated_at']]);

    expect($response->json('data'))->not->toHaveKeys(['deleted_at', 'bitrix_group_id', 'bitrix_synced_at']);
    expectNoInternalUserFields($response->json('data.owner'));
})->with([
    'add member' => ['POST', '/api/v1/projects/{project}/members', ['user_id' => '{other}'], 'Member added to project successfully'],
    'remove member' => ['DELETE', '/api/v1/projects/{project}/members/{user}', [], 'Member removed from project successfully'],
    'archive' => ['PATCH', '/api/v1/projects/{project}/archive', [], 'Project status changed to archived successfully'],
]);

it('lists project options as id, title and status', function (): void {
    $this->getJson('/api/v1/projects/options')
        ->assertOk()
        ->assertJsonFragment(['id' => $this->project->id, 'title' => $this->project->title, 'status' => $this->project->status->value])
        ->assertJsonStructure(['data' => [['id', 'title', 'status']]]);
});

it('pages the activity log with who did it, and nothing internal about them', function (string $uri): void {
    $uri = str_replace('{project}', (string) $this->project->id, $uri);

    $response = $this->getJson($uri)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success', 'message',
            'data' => [['id', 'action', 'description', 'metadata', 'project_id', 'task_id', 'user' => ['id', 'name', 'email'], 'created_at']],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

    expect($response->json('data.0.user'))->toHaveKeys(['id', 'name', 'email'])
        ->and($response->json('data.0.action'))->toBeString();
    expectNoInternalUserFields($response->json('data.0.user'));
})->with([
    'one project' => ['/api/v1/projects/{project}/activities'],
    'all' => ['/api/v1/activities'],
]);

it('pages a project\'s tasks like the task list', function (): void {
    $response = $this->getJson("/api/v1/projects/{$this->project->id}/tasks")
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'title', 'status', 'priority', 'due_date', 'assignee' => ['id', 'name', 'email']]], 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);

    expect($response->json('data.0'))->not->toHaveKeys(['deleted_at', 'bitrix_synced_at', 'status_definition']);
});
