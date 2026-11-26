<?php

declare(strict_types=1);

use App\Enums\BitrixSyncFailureStatus;
use App\Enums\BitrixSyncOperation;
use App\Exceptions\Bitrix\BitrixException;
use App\Exceptions\Bitrix\BitrixSyncFailureClosedException;
use App\Exceptions\Task\TimerRunningOnAnotherTaskException;
use App\Models\BitrixSyncFailure;
use App\Repositories\Contracts\BitrixSyncFailureRepositoryInterface;
use App\Repositories\Contracts\FailedJobRepositoryInterface;
use App\Services\BitrixSyncFailureService;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-11-26 10:00:00');
    $this->repository = Mockery::mock(BitrixSyncFailureRepositoryInterface::class);
    $this->failedJobs = Mockery::mock(FailedJobRepositoryInterface::class);
    $this->service = new BitrixSyncFailureService($this->repository, $this->failedJobs);
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** @param  array<string, mixed>  $attributes */
function failureRow(array $attributes = []): BitrixSyncFailure
{
    return (new BitrixSyncFailure)->forceFill([
        'id' => 5, 'operation' => BitrixSyncOperation::Task, 'subject' => '12', 'error' => 'x', 'attempts' => 1,
        'status' => BitrixSyncFailureStatus::Pending, 'failed_job_uuid' => 'old-uuid', 'last_failed_at' => CarbonImmutable::now(),
        ...$attributes,
    ]);
}

it('opens a failure the first time an operation runs out of tries', function (): void {
    $this->repository->shouldReceive('findOpen')->with(BitrixSyncOperation::Import, 'users')->andReturnNull();
    $this->repository->shouldReceive('create')->once()->withArgs(fn (array $row): bool => $row['operation'] === BitrixSyncOperation::Import
        && $row['subject'] === 'users' && $row['attempts'] === 1 && $row['error'] === 'timed out'
        && $row['status'] === BitrixSyncFailureStatus::Pending && $row['failed_job_uuid'] === 'uuid-1')
        ->andReturn(new BitrixSyncFailure);

    $this->service->record(BitrixSyncOperation::Import, 'users', BitrixException::unreachable('timed out'), 'uuid-1');
});

it('counts a repeat on the open failure and drops the older failed_jobs row', function (): void {
    $open = failureRow(['attempts' => 2, 'status' => BitrixSyncFailureStatus::Retrying]);
    $this->repository->shouldReceive('findOpen')->andReturn($open);
    $this->failedJobs->shouldReceive('forget')->once()->with('old-uuid');
    $this->repository->shouldReceive('update')->once()->withArgs(fn (BitrixSyncFailure $failure, array $row): bool => $failure === $open
        && $row['attempts'] === 3 && $row['status'] === BitrixSyncFailureStatus::Pending && $row['failed_job_uuid'] === 'uuid-2')
        ->andReturn($open);

    $this->service->record(BitrixSyncOperation::Task, '12', BitrixException::unreachable('again'), 'uuid-2');
});

it('keeps errors short', function (): void {
    $this->repository->shouldReceive('findOpen')->andReturnNull();
    $this->repository->shouldReceive('create')->once()
        ->withArgs(fn (array $row): bool => mb_strlen($row['error']) === BitrixSyncFailureService::MAX_ERROR_LENGTH)
        ->andReturn(new BitrixSyncFailure);

    $this->service->record(BitrixSyncOperation::Task, '12', BitrixException::unreachable(str_repeat('e', 5000)), null);
});

it('closes the open failure when the operation succeeds, and does nothing when none is open', function (): void {
    $open = failureRow();
    $this->repository->shouldReceive('findOpen')->with(BitrixSyncOperation::Task, '12')->andReturn($open);
    $this->repository->shouldReceive('findOpen')->with(BitrixSyncOperation::Task, '13')->andReturnNull();
    $this->failedJobs->shouldReceive('forget')->once()->with('old-uuid');
    $this->repository->shouldReceive('update')->once()->withArgs(fn (BitrixSyncFailure $failure, array $row): bool => $row['status'] === BitrixSyncFailureStatus::Resolved
        && $row['closed_at'] instanceof CarbonImmutable)
        ->andReturn($open);

    $this->service->resolve(BitrixSyncOperation::Task, '12');
    $this->service->resolve(BitrixSyncOperation::Task, '13');
});

it('marks a pending failure as retrying', function (): void {
    $failure = failureRow();
    $this->failedJobs->shouldReceive('forget')->once()->with('old-uuid');
    $this->repository->shouldReceive('update')->once()->withArgs(fn (BitrixSyncFailure $row, array $attributes): bool => $attributes['status'] === BitrixSyncFailureStatus::Retrying
        && $attributes['retried_at']->equalTo(CarbonImmutable::now()) && $attributes['failed_job_uuid'] === null)
        ->andReturn($failure);

    expect($this->service->markRetrying($failure))->toBe($failure);
});

it('refuses a retry while the last one is fresh, and allows it once stale', function (): void {
    $fresh = failureRow(['status' => BitrixSyncFailureStatus::Retrying, 'retried_at' => CarbonImmutable::now()->subMinutes(9)]);
    expect(fn () => $this->service->markRetrying($fresh))->toThrow(BitrixSyncFailureClosedException::class, 'already queued');

    $stale = failureRow(['status' => BitrixSyncFailureStatus::Retrying, 'retried_at' => CarbonImmutable::now()->subMinutes(11), 'failed_job_uuid' => null]);
    $this->repository->shouldReceive('update')->once()->andReturn($stale);
    $this->service->markRetrying($stale);
});

it('refuses to retry or dismiss a closed failure', function (BitrixSyncFailureStatus $status): void {
    $closed = failureRow(['status' => $status]);

    expect(fn () => $this->service->markRetrying($closed))->toThrow(BitrixSyncFailureClosedException::class, 'already closed')
        ->and(fn () => $this->service->dismiss($closed))->toThrow(BitrixSyncFailureClosedException::class, 'already closed');
})->with([BitrixSyncFailureStatus::Resolved, BitrixSyncFailureStatus::Dismissed]);

it('dismisses an open failure', function (): void {
    $failure = failureRow(['failed_job_uuid' => null]);
    $this->failedJobs->shouldNotReceive('forget');
    $this->repository->shouldReceive('update')->once()->withArgs(fn (BitrixSyncFailure $row, array $attributes): bool => $attributes['status'] === BitrixSyncFailureStatus::Dismissed)
        ->andReturn($failure);

    $this->service->dismiss($failure);
});

it('stores what is safe to show: redacted Bitrix text, our own messages, only the class of anything else', function (): void {
    $leak = new RuntimeException("SQLSTATE[23000]: insert into users (email) values ('someone@example.com')");

    expect($this->service->describe(BitrixException::unreachable('cURL error 28 for https://portal.test/rest/x?auth=secret-token')))
        ->toBe('cURL error 28 for https://portal.test/rest/x?[redacted]')
        ->and($this->service->describe(new TimerRunningOnAnotherTaskException))->toBe('Your timer is running on another task. Stop it there.')
        ->and($this->service->describe($leak))->toBe('Internal error (RuntimeException). See the application log.')
        ->and($this->service->describe(null))->toBe('Unknown error');
});

it('gives an open failure the outer job\'s failed_jobs row, without counting another attempt', function (): void {
    $open = failureRow(['attempts' => 1, 'failed_job_uuid' => 'inner-sync-uuid']);
    $this->repository->shouldReceive('findOpen')->with(BitrixSyncOperation::Import, 'tasks-changes')->andReturn($open);
    $this->failedJobs->shouldReceive('forget')->once()->with('inner-sync-uuid');
    $this->repository->shouldReceive('update')->once()->with($open, ['failed_job_uuid' => 'outer-uuid'])->andReturn($open);

    $this->service->attachFailedJob(BitrixSyncOperation::Import, 'tasks-changes', BitrixException::unreachable('x'), 'outer-uuid');
});

it('records the failure itself when nothing inside the outer job did', function (): void {
    $this->repository->shouldReceive('findOpen')->andReturnNull();
    $this->repository->shouldReceive('create')->once()
        ->withArgs(fn (array $row): bool => $row['subject'] === 'tasks-changes' && $row['failed_job_uuid'] === 'outer-uuid')
        ->andReturn(new BitrixSyncFailure);

    $this->service->attachFailedJob(BitrixSyncOperation::Import, 'tasks-changes', null, 'outer-uuid');
});
