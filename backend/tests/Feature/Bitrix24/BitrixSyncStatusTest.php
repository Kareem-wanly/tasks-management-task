<?php

declare(strict_types=1);

use App\Enums\BitrixSyncFailureStatus;
use App\Enums\BitrixSyncOperation;
use App\Enums\OutboundOpAction;
use App\Enums\OutboundOpEntity;
use App\Enums\OutboundOpStatus;
use App\Enums\SyncCursorStatus;
use App\Jobs\Bitrix\ImportBitrixChatJob;
use App\Jobs\Bitrix\ImportBitrixChecklistsJob;
use App\Jobs\Bitrix\ImportBitrixFilesJob;
use App\Jobs\Bitrix\ImportBitrixProjectsJob;
use App\Jobs\Bitrix\ImportBitrixResultsJob;
use App\Jobs\Bitrix\ImportBitrixTasksJob;
use App\Jobs\Bitrix\ImportBitrixTimeJob;
use App\Jobs\Bitrix\ImportBitrixUsersJob;
use App\Jobs\Bitrix\SyncBitrixChangesJob;
use App\Models\BitrixOutboundOp;
use App\Models\BitrixSyncCursor;
use App\Models\BitrixSyncFailure;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed();
    $this->travelTo(now()->startOfMinute());
});

function syncManager(): User
{
    $role = Role::create(['name' => 'sync_'.uniqid(), 'display_name' => 'Sync', 'description' => 'x']);
    $role->permissions()->sync(Permission::where('name', 'bitrix_sync.manage')->pluck('id'));
    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

function teamMember(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'Member')->first());

    return $user;
}

function cursor(string $entity, SyncCursorStatus $status, array $attributes = []): void
{
    BitrixSyncCursor::create(['entity' => $entity, 'status' => $status, 'started_at' => now()->subMinutes(5), ...$attributes]);
}

function outboundOp(OutboundOpStatus $status, int $minutesAgo, int $seconds = 0): void
{
    $op = BitrixOutboundOp::create([
        'entity' => OutboundOpEntity::Task, 'bitrix_id' => 1, 'action' => OutboundOpAction::Update,
        'fingerprint' => null, 'status' => $status, 'user_id' => User::factory()->create()->id,
    ]);
    $op->forceFill(['created_at' => now()->subMinutes($minutesAgo)->subSeconds($seconds)])->save();
}

it('is closed to users without the permission', function (): void {
    Sanctum::actingAs(teamMember());

    $this->getJson('/api/v1/bitrix/sync')->assertForbidden();
    $this->postJson('/api/v1/bitrix/sync', ['mode' => 'changes'])->assertForbidden();
});

it('shows every entity in import order, then the changes sync', function (): void {
    Sanctum::actingAs(syncManager());
    cursor('users', SyncCursorStatus::Completed, ['processed' => 12, 'total' => 12, 'last_synced_at' => now()->subHour()]);
    cursor('tasks', SyncCursorStatus::Failed, ['error' => 'Bitrix24 is unreachable']);
    cursor('projects', SyncCursorStatus::Running, ['processed' => 1, 'total' => 4]);
    cursor('checklists', SyncCursorStatus::Running, ['started_at' => now()->subHours(2)]);

    $response = $this->getJson('/api/v1/bitrix/sync')->assertOk();

    expect($response->json('data.entities.*.entity'))->toBe(['users', 'projects', 'tasks', 'checklists', 'time', 'files', 'results', 'chat', 'tasks-changes'])
        ->and($response->json('data.entities.*.state'))->toBe(['completed', 'running', 'failed', 'stalled', 'never', 'never', 'never', 'never', 'never']);
    $response->assertJsonPath('data.is_running', true)
        ->assertJsonPath('data.entities.0.processed', 12)
        ->assertJsonPath('data.entities.0.last_synced_at', now()->subHour()->toIso8601String())
        ->assertJsonPath('data.entities.1.total', 4)
        ->assertJsonPath('data.entities.2.error', 'Bitrix24 is unreachable')
        ->assertJsonPath('data.entities.4.started_at', null);
});

it('counts open failures, recent conflicts and changes Bitrix never echoed', function (): void {
    Sanctum::actingAs(syncManager());
    foreach ([BitrixSyncFailureStatus::Pending, BitrixSyncFailureStatus::Retrying, BitrixSyncFailureStatus::Resolved, BitrixSyncFailureStatus::Dismissed] as $i => $status) {
        BitrixSyncFailure::create(['operation' => BitrixSyncOperation::Task, 'subject' => (string) $i, 'error' => 'x', 'status' => $status, 'last_failed_at' => now()]);
    }
    outboundOp(OutboundOpStatus::Conflict, minutesAgo: 30);
    outboundOp(OutboundOpStatus::Conflict, minutesAgo: 60 * 25);
    outboundOp(OutboundOpStatus::Sent, minutesAgo: 10);
    outboundOp(OutboundOpStatus::Sent, minutesAgo: 0, seconds: 10);
    outboundOp(OutboundOpStatus::Confirmed, minutesAgo: 10);

    $this->getJson('/api/v1/bitrix/sync')->assertOk()
        ->assertJsonPath('data.is_running', false)
        ->assertJsonPath('data.failures', 2)
        ->assertJsonPath('data.conflicts', 1)
        ->assertJsonPath('data.unconfirmed', 1);
});

it('lets administrators in without the permission', function (): void {
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::where('name', 'Administrator')->first());
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/bitrix/sync')->assertOk();
});

it('queues a changes sync on the bitrix queue', function (): void {
    Bus::fake();
    Sanctum::actingAs(syncManager());

    $this->postJson('/api/v1/bitrix/sync', ['mode' => 'changes'])->assertStatus(202)->assertJsonPath('data.is_running', false);

    Bus::assertDispatched(SyncBitrixChangesJob::class, fn (SyncBitrixChangesJob $job): bool => $job->queue === 'bitrix');
});

it('queues a full import as one chain, in order', function (): void {
    Bus::fake();
    Sanctum::actingAs(syncManager());

    $this->postJson('/api/v1/bitrix/sync', ['mode' => 'full'])->assertStatus(202);

    Bus::assertChained([
        ImportBitrixUsersJob::class, ImportBitrixProjectsJob::class, ImportBitrixTasksJob::class, ImportBitrixChecklistsJob::class,
        ImportBitrixTimeJob::class, ImportBitrixFilesJob::class, ImportBitrixResultsJob::class, ImportBitrixChatJob::class,
    ]);
});

it('refuses a second sync while one runs', function (): void {
    Bus::fake();
    Sanctum::actingAs(syncManager());
    cursor('tasks', SyncCursorStatus::Running);

    $this->postJson('/api/v1/bitrix/sync', ['mode' => 'full'])->assertStatus(409)
        ->assertJsonPath('message', 'A Bitrix24 sync is already running. Wait for it to finish.');

    Bus::assertNothingDispatched();
});

it('refuses a second press before the worker picks the first one up', function (): void {
    Bus::fake();
    Sanctum::actingAs(syncManager());

    $this->postJson('/api/v1/bitrix/sync', ['mode' => 'changes'])->assertStatus(202);
    $this->postJson('/api/v1/bitrix/sync', ['mode' => 'full'])->assertStatus(409)
        ->assertJsonPath('message', 'A Bitrix24 sync is already queued. It starts when the background worker picks it up.');

    Bus::assertDispatchedTimes(SyncBitrixChangesJob::class, 1);
    Bus::assertNotDispatched(ImportBitrixUsersJob::class);
});

it('takes a new press as soon as the queued sync has run, without waiting', function (): void {
    Bus::fake();
    Sanctum::actingAs(syncManager());

    $this->postJson('/api/v1/bitrix/sync', ['mode' => 'changes'])->assertStatus(202);
    $this->travel(5)->seconds();
    // The worker picked it up and finished it a few seconds later.
    cursor('tasks-changes', SyncCursorStatus::Completed, ['started_at' => now(), 'last_synced_at' => now()]);

    $this->postJson('/api/v1/bitrix/sync', ['mode' => 'full'])->assertStatus(202);
});

it('stops blocking once a request the worker never picked up expires', function (): void {
    Bus::fake();
    Sanctum::actingAs(syncManager());

    $this->postJson('/api/v1/bitrix/sync', ['mode' => 'changes'])->assertStatus(202);
    $this->travel(11)->minutes();

    $this->postJson('/api/v1/bitrix/sync', ['mode' => 'changes'])->assertStatus(202);
});

it('starts again over a run that died with its worker', function (): void {
    Bus::fake();
    Sanctum::actingAs(syncManager());
    cursor('tasks', SyncCursorStatus::Running, ['started_at' => now()->subHours(2)]);

    $this->postJson('/api/v1/bitrix/sync', ['mode' => 'changes'])->assertStatus(202);
});

it('accepts only the known modes', function (): void {
    Sanctum::actingAs(syncManager());

    $this->postJson('/api/v1/bitrix/sync', ['mode' => 'everything'])->assertUnprocessable()->assertJsonValidationErrors('mode');
    $this->postJson('/api/v1/bitrix/sync', [])->assertUnprocessable()->assertJsonValidationErrors('mode');
});
