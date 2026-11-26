<?php

declare(strict_types=1);

use App\Enums\BitrixSyncFailureStatus;
use App\Enums\BitrixSyncOperation;
use App\Enums\SyncCursorStatus;
use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\Contracts\HasBitrixToken;
use App\Jobs\Bitrix\ImportBitrixChatJob;
use App\Jobs\Bitrix\ImportBitrixChecklistsJob;
use App\Jobs\Bitrix\ImportBitrixFilesJob;
use App\Jobs\Bitrix\ImportBitrixProjectsJob;
use App\Jobs\Bitrix\ImportBitrixResultsJob;
use App\Jobs\Bitrix\ImportBitrixTasksJob;
use App\Jobs\Bitrix\ImportBitrixTimeJob;
use App\Jobs\Bitrix\ImportBitrixUsersJob;
use App\Jobs\Bitrix\SyncBitrixChangesJob;
use App\Jobs\Bitrix\SyncBitrixProjectJob;
use App\Jobs\Bitrix\SyncBitrixTaskChatJob;
use App\Jobs\Bitrix\SyncBitrixTaskJob;
use App\Models\BitrixSyncCursor;
use App\Models\BitrixSyncFailure;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Contracts\BitrixSyncServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed();
    $this->travelTo(now()->startOfMinute());
});

function failureManager(): User
{
    $role = Role::create(['name' => 'sync_'.uniqid(), 'display_name' => 'Sync', 'description' => 'x']);
    $role->permissions()->sync(Permission::where('name', 'bitrix_sync.manage')->pluck('id'));
    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

/** @param  array<string, mixed>  $attributes */
function deadLetter(BitrixSyncOperation $operation, string $subject, array $attributes = []): BitrixSyncFailure
{
    return BitrixSyncFailure::create([
        'operation' => $operation, 'subject' => $subject, 'error' => 'Bitrix24 is unreachable', 'attempts' => 1,
        'status' => BitrixSyncFailureStatus::Pending, 'last_failed_at' => now(), ...$attributes,
    ]);
}

function failedJobRow(): string
{
    $uuid = (string) Str::uuid();
    DB::table('failed_jobs')->insert([
        'uuid' => $uuid, 'connection' => 'database', 'queue' => 'bitrix', 'payload' => '{}', 'exception' => 'Boom', 'failed_at' => now(),
    ]);

    return $uuid;
}

function fakeServiceAccount(): void
{
    test()->useBitrixServiceAccount(new class implements HasBitrixToken
    {
        public function bitrixToken(): BitrixToken
        {
            return new BitrixToken('service-access', 'service-refresh', CarbonImmutable::now()->addHour());
        }

        public function bitrixTokenLockKey(): string
        {
            return 'bitrix-token:test';
        }
    });
}

it('keeps the failures to the sync managers', function (): void {
    $failure = deadLetter(BitrixSyncOperation::Task, '12');
    $member = User::factory()->create();
    $member->roles()->attach(Role::where('name', 'Member')->first());
    Sanctum::actingAs($member);

    $this->getJson('/api/v1/bitrix/sync/failures')->assertForbidden();
    $this->postJson("/api/v1/bitrix/sync/failures/{$failure->id}/retry")->assertForbidden();
    $this->deleteJson("/api/v1/bitrix/sync/failures/{$failure->id}")->assertForbidden();
    expect($failure->fresh()->status)->toBe(BitrixSyncFailureStatus::Pending);
});

it('lists the open failures, most recent first', function (): void {
    Sanctum::actingAs(failureManager());
    deadLetter(BitrixSyncOperation::Task, '12', ['last_failed_at' => now()->subHour()]);
    deadLetter(BitrixSyncOperation::Import, 'tasks', ['attempts' => 3, 'error' => 'timed out']);
    deadLetter(BitrixSyncOperation::Project, '4', ['status' => BitrixSyncFailureStatus::Retrying, 'retried_at' => now(), 'last_failed_at' => now()->subMinutes(5)]);
    deadLetter(BitrixSyncOperation::Task, '13', ['status' => BitrixSyncFailureStatus::Resolved, 'closed_at' => now()]);
    deadLetter(BitrixSyncOperation::Task, '14', ['status' => BitrixSyncFailureStatus::Dismissed, 'closed_at' => now()]);

    $response = $this->getJson('/api/v1/bitrix/sync/failures')->assertOk()->assertJsonCount(3, 'data');

    expect($response->json('data.*.subject'))->toBe(['tasks', '4', '12'])
        ->and($response->json('data.0'))->toMatchArray([
            'operation' => 'import', 'attempts' => 3, 'error' => 'timed out', 'status' => 'pending',
            'last_failed_at' => now()->toIso8601String(), 'retried_at' => null, 'can_retry' => true,
        ])
        // Retried moments ago: no Retry button until it goes stale.
        ->and($response->json('data.1'))->toMatchArray(['status' => 'retrying', 'can_retry' => false]);
    $this->getJson('/api/v1/bitrix/sync')->assertJsonPath('data.failures', 3);
});

it('retries an event sync by queueing its job again', function (BitrixSyncOperation $operation, string $job): void {
    Bus::fake();
    Sanctum::actingAs(failureManager());
    $uuid = failedJobRow();
    $failure = deadLetter($operation, '12', ['failed_job_uuid' => $uuid]);

    $this->postJson("/api/v1/bitrix/sync/failures/{$failure->id}/retry")->assertStatus(202)
        ->assertJsonPath('data.status', 'retrying')
        ->assertJsonPath('data.retried_at', now()->toIso8601String());

    Bus::assertDispatched($job, fn (object $queued): bool => $queued->queue === 'bitrix'
        && ($queued->bitrixTaskId ?? $queued->bitrixGroupId) === 12);
    // Laravel's own copy is gone, so `queue:retry all` can't run it a second time.
    expect(DB::table('failed_jobs')->where('uuid', $uuid)->exists())->toBeFalse()
        ->and($failure->fresh()->failed_job_uuid)->toBeNull();
})->with([
    'task' => [BitrixSyncOperation::Task, SyncBitrixTaskJob::class],
    'project' => [BitrixSyncOperation::Project, SyncBitrixProjectJob::class],
    'task chat' => [BitrixSyncOperation::TaskChat, SyncBitrixTaskChatJob::class],
]);

it('retries an import pass with the passes after it', function (): void {
    Bus::fake();
    Sanctum::actingAs(failureManager());
    $failure = deadLetter(BitrixSyncOperation::Import, 'tasks');

    $this->postJson("/api/v1/bitrix/sync/failures/{$failure->id}/retry")->assertStatus(202);

    Bus::assertChained([
        ImportBitrixTasksJob::class, ImportBitrixChecklistsJob::class, ImportBitrixTimeJob::class,
        ImportBitrixFilesJob::class, ImportBitrixResultsJob::class, ImportBitrixChatJob::class,
    ]);
    Bus::assertNotDispatched(ImportBitrixUsersJob::class);
    Bus::assertNotDispatched(ImportBitrixProjectsJob::class);
});

it('retries the changes sync on its own', function (): void {
    Bus::fake();
    Sanctum::actingAs(failureManager());
    $failure = deadLetter(BitrixSyncOperation::Import, 'tasks-changes');

    $this->postJson("/api/v1/bitrix/sync/failures/{$failure->id}/retry")->assertStatus(202);

    Bus::assertDispatched(SyncBitrixChangesJob::class);
    Bus::assertNotDispatched(ImportBitrixTasksJob::class);
});

it('refuses a second retry while the first waits for the worker, until it goes stale', function (): void {
    Bus::fake();
    Sanctum::actingAs(failureManager());
    $failure = deadLetter(BitrixSyncOperation::Task, '12');

    $this->postJson("/api/v1/bitrix/sync/failures/{$failure->id}/retry")->assertStatus(202);
    $this->postJson("/api/v1/bitrix/sync/failures/{$failure->id}/retry")->assertStatus(409)
        ->assertJsonPath('message', 'A retry of this failure is already queued. It starts when the background worker picks it up.');
    Bus::assertDispatchedTimes(SyncBitrixTaskJob::class, 1);

    $this->travel(11)->minutes();
    $this->postJson("/api/v1/bitrix/sync/failures/{$failure->id}/retry")->assertStatus(202);
    Bus::assertDispatchedTimes(SyncBitrixTaskJob::class, 2);
});

it('dismisses a failure, once', function (): void {
    Sanctum::actingAs(failureManager());
    $uuid = failedJobRow();
    $failure = deadLetter(BitrixSyncOperation::Import, 'files', ['failed_job_uuid' => $uuid]);

    $this->deleteJson("/api/v1/bitrix/sync/failures/{$failure->id}")->assertOk();

    expect($failure->fresh())->status->toBe(BitrixSyncFailureStatus::Dismissed)->closed_at->not->toBeNull()
        ->and(DB::table('failed_jobs')->where('uuid', $uuid)->exists())->toBeFalse();
    $this->deleteJson("/api/v1/bitrix/sync/failures/{$failure->id}")->assertStatus(409);
    $this->postJson("/api/v1/bitrix/sync/failures/{$failure->id}/retry")->assertStatus(409)
        ->assertJsonPath('message', 'This failure is already closed. Refresh the page.');
    $this->getJson('/api/v1/bitrix/sync/failures')->assertJsonCount(0, 'data');
});

it('turns an event job that ran out of tries into a dead letter, and counts repeats', function (): void {
    $job = new SyncBitrixTaskJob(12);

    $job->failed(BitrixException::unreachable('timed out'));
    $job->failed(BitrixException::unreachable('timed out again'));

    $failure = BitrixSyncFailure::sole();
    expect($failure)
        ->operation->toBe(BitrixSyncOperation::Task)
        ->subject->toBe('12')
        ->attempts->toBe(2)
        ->status->toBe(BitrixSyncFailureStatus::Pending)
        ->error->toContain('timed out again');
});

it('closes the dead letter when the same sync next succeeds', function (): void {
    $retrying = deadLetter(BitrixSyncOperation::TaskChat, '77', ['status' => BitrixSyncFailureStatus::Retrying, 'retried_at' => now()]);
    $other = deadLetter(BitrixSyncOperation::TaskChat, '78');

    // No such task here: nothing to fetch, so the sync succeeds at once.
    resolve(BitrixSyncServiceInterface::class)->syncTaskChat(77);

    expect($retrying->fresh())->status->toBe(BitrixSyncFailureStatus::Resolved)->closed_at->not->toBeNull()
        ->and($other->fresh()->status)->toBe(BitrixSyncFailureStatus::Pending);
});

it('records a failed import pass, and closes it when the pass next completes', function (): void {
    config(['bitrix.portal' => 'portal.test']);
    fakeServiceAccount();
    $network = new ArrayObject(['online' => false]);
    Http::fake(['https://portal.test/rest/user.get.json' => function () use ($network) {
        if (! $network['online']) {
            throw new ConnectionException('Could not resolve host');
        }

        return Http::response(['result' => [], 'total' => 0]);
    }]);

    expect(fn () => dispatch_sync(new ImportBitrixUsersJob))->toThrow(BitrixException::class);

    $failure = BitrixSyncFailure::sole();
    expect($failure)->operation->toBe(BitrixSyncOperation::Import)->subject->toBe('users')->status->toBe(BitrixSyncFailureStatus::Pending)
        ->and(BitrixSyncCursor::where('entity', 'users')->value('status'))->toBe(SyncCursorStatus::Failed);

    $network['online'] = true;
    dispatch_sync(new ImportBitrixUsersJob);

    expect($failure->fresh()->status)->toBe(BitrixSyncFailureStatus::Resolved);
});

it('keeps the worker\'s failed_jobs row with the dead letter', function (): void {
    $job = (new SyncBitrixTaskJob(12))->setJob(Mockery::mock(Illuminate\Contracts\Queue\Job::class, ['uuid' => 'worker-uuid']));

    $job->failed(BitrixException::unreachable('timed out'));

    expect(BitrixSyncFailure::sole()->failed_job_uuid)->toBe('worker-uuid');
});

it('never stores or shows the raw text of an unexpected error (SEC-5)', function (): void {
    Sanctum::actingAs(failureManager());

    (new SyncBitrixProjectJob(4))->failed(new RuntimeException("insert into users (email) values ('someone@example.com')"));

    $this->getJson('/api/v1/bitrix/sync/failures')->assertOk()
        ->assertJsonPath('data.0.error', 'Internal error (RuntimeException). See the application log.');
    expect(BitrixSyncFailure::sole()->error)->not->toContain('example.com');
});

it('closes a task or project dead letter when the next event sync of it succeeds', function (string $method, BitrixSyncOperation $operation): void {
    $failure = deadLetter($operation, '55');
    // Our own change echoing back: the sync ends early, successfully.
    $this->mock(App\Integrations\Bitrix24\Contracts\BitrixRecordFetcherInterface::class, function ($fetch): void {
        $fetch->shouldReceive('task')->andReturnNull();
        $fetch->shouldReceive('group')->andReturn(['id' => 55]);
    });
    $this->mock(App\Integrations\Bitrix24\Contracts\BitrixEchoGuardInterface::class, function ($echo): void {
        $echo->shouldReceive('isTaskEcho')->andReturnTrue();
        $echo->shouldReceive('isProjectEcho')->andReturnTrue();
    });
    $this->mock(App\Integrations\Bitrix24\Contracts\BitrixProjectMapperInterface::class, function ($mapper): void {
        $mapper->shouldReceive('attributes')->andReturn([]);
        $mapper->shouldReceive('isClosed')->andReturnFalse();
    });

    resolve(BitrixSyncServiceInterface::class)->{$method}(55);

    expect($failure->fresh()->status)->toBe(BitrixSyncFailureStatus::Resolved);
})->with([
    'task' => ['syncTask', BitrixSyncOperation::Task],
    'project' => ['syncProject', BitrixSyncOperation::Project],
]);

it('files a failure inside the changes sync under that sync, with the outer job\'s row, and retries the changes sync', function (): void {
    config(['bitrix.portal' => 'portal.test']);
    fakeServiceAccount();
    BitrixSyncCursor::create(['entity' => 'tasks', 'status' => SyncCursorStatus::Completed, 'started_at' => now()->subHour(), 'last_synced_at' => now()->subHour()]);
    Http::fake(fn (): never => throw new ConnectionException('Could not resolve host'));
    $outer = (new SyncBitrixChangesJob)->setJob(Mockery::mock(Illuminate\Contracts\Queue\Job::class, ['uuid' => 'outer-uuid']));

    try {
        $outer->handle(resolve(App\Services\Contracts\BitrixImportServiceInterface::class));
        $this->fail('The changes sync should have failed.');
    } catch (BitrixException $exception) {
        $outer->failed($exception);
    }

    $failure = BitrixSyncFailure::sole();
    expect($failure)->operation->toBe(BitrixSyncOperation::Import)->subject->toBe('tasks-changes')->attempts->toBe(1)
        ->failed_job_uuid->toBe('outer-uuid');

    Bus::fake();
    Sanctum::actingAs(failureManager());
    $this->postJson("/api/v1/bitrix/sync/failures/{$failure->id}/retry")->assertStatus(202);
    Bus::assertDispatched(SyncBitrixChangesJob::class);
    Bus::assertNotDispatched(ImportBitrixProjectsJob::class);
});

it('refuses to retry an import while a sync runs or waits, but still retries event syncs', function (): void {
    Bus::fake();
    Sanctum::actingAs(failureManager());
    $import = deadLetter(BitrixSyncOperation::Import, 'files');
    $task = deadLetter(BitrixSyncOperation::Task, '12');
    BitrixSyncCursor::create(['entity' => 'tasks', 'status' => SyncCursorStatus::Running, 'started_at' => now()->subMinute()]);

    $this->postJson("/api/v1/bitrix/sync/failures/{$import->id}/retry")->assertStatus(409)
        ->assertJsonPath('message', 'A Bitrix24 sync is already running. Wait for it to finish.');
    expect($import->fresh()->status)->toBe(BitrixSyncFailureStatus::Pending);
    $this->postJson("/api/v1/bitrix/sync/failures/{$task->id}/retry")->assertStatus(202);
    Bus::assertNotDispatched(ImportBitrixFilesJob::class);
});

it('counts an import retry as a queued sync, so "sync now" waits for it', function (): void {
    Bus::fake();
    Sanctum::actingAs(failureManager());
    $failure = deadLetter(BitrixSyncOperation::Import, 'chat');

    $this->postJson("/api/v1/bitrix/sync/failures/{$failure->id}/retry")->assertStatus(202);
    $this->postJson('/api/v1/bitrix/sync', ['mode' => 'changes'])->assertStatus(409)
        ->assertJsonPath('message', 'A Bitrix24 sync is already queued. It starts when the background worker picks it up.');
});
