<?php

declare(strict_types=1);

use App\Jobs\Bitrix\ImportBitrixTasksJob;
use App\Jobs\Bitrix\ImportBitrixUsersJob;
use App\Repositories\Contracts\BitrixSyncCursorRepositoryInterface;
use App\Services\Contracts\BitrixImportServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;

/** Holds the lock a running import of the job's entity would hold. */
function holdImportLock(object $job): void
{
    $overlap = $job->middleware()[0];
    expect($overlap)->toBeInstanceOf(WithoutOverlapping::class);
    Cache::lock($overlap->getLockKey($job), 3600)->get();
}

it('drops an import while the same import is still running', function (): void {
    $job = new ImportBitrixUsersJob;
    holdImportLock($job);
    $this->mock(BitrixImportServiceInterface::class)->shouldNotReceive('importUsers');

    dispatch_sync($job);
});

it('runs the import when no other run holds it, and frees it after', function (): void {
    $this->mock(BitrixImportServiceInterface::class)->shouldReceive('importUsers')->twice();

    dispatch_sync(new ImportBitrixUsersJob);
    dispatch_sync(new ImportBitrixUsersJob);
});

it('locks each import on its own: a running users import does not hold back tasks', function (): void {
    holdImportLock(new ImportBitrixUsersJob);
    $this->mock(BitrixImportServiceInterface::class)->shouldReceive('importTasks')->once();

    dispatch_sync(new ImportBitrixTasksJob);
});

it('locks the changes sync apart from the full task import', function (): void {
    holdImportLock(new ImportBitrixTasksJob);
    $this->mock(BitrixImportServiceInterface::class)->shouldReceive('importTasks')->once();

    dispatch_sync(new ImportBitrixTasksJob(CarbonImmutable::now()->subHour()));
});

it('reports every saved page of the watched import, and nothing of the others', function (): void {
    $cursors = resolve(BitrixSyncCursorRepositoryInterface::class);
    $reported = [];
    $cursors->watch('users', function (int $processed, int $total) use (&$reported): void {
        $reported[] = [$processed, $total];
    });

    $users = $cursors->start('users');
    $cursors->advance($cursors->start('projects'), 5, 5);
    $cursors->advance($users, 50, 120);
    $cursors->advance($users, 50, 120);

    expect($reported)->toBe([[0, 0], [50, 120], [100, 120]]);
});
