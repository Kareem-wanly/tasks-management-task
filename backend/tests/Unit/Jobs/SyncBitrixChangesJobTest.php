<?php

declare(strict_types=1);

use App\Jobs\Bitrix\SyncBitrixChangesJob;
use App\Services\Contracts\BitrixImportServiceInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;

it('runs the changes sync of the import service, once, on the bitrix queue', function (): void {
    $import = Mockery::mock(BitrixImportServiceInterface::class);
    $import->shouldReceive('syncChanges')->once()->andReturnNull();

    $job = new SyncBitrixChangesJob;
    $job->handle($import);

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->queue)->toBe('bitrix')
        ->and($job->tries)->toBe(1)
        ->and($job->timeout)->toBe(3600);
});
