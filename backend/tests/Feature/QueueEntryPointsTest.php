<?php

declare(strict_types=1);

use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Bitrix\BitrixException;
use App\Exceptions\Project\ProjectHasUnfinishedTasksException;
use App\Jobs\Bitrix\SyncBitrixProjectJob;
use App\Jobs\Bitrix\SyncBitrixTaskChatJob;
use App\Jobs\Bitrix\SyncBitrixTaskJob;
use App\Services\Contracts\BitrixSyncServiceInterface;
use Illuminate\Contracts\Debug\ExceptionHandler;

/*
 * PR8a: the event jobs are entry points (ARC-12) that call one service
 * method, and expected business answers stay out of the error log.
 */
it('runs each event job through one BitrixSyncService call', function (object $job, string $method, int $id): void {
    $this->mock(BitrixSyncServiceInterface::class)->shouldReceive($method)->once()->with($id);

    dispatch_sync($job);
})->with([
    'task' => [new SyncBitrixTaskJob(15), 'syncTask', 15],
    'project' => [new SyncBitrixProjectJob(4), 'syncProject', 4],
    'task chat' => [new SyncBitrixTaskChatJob(23), 'syncTaskChat', 23],
]);

it('declares its retries, backoff, timeout and failure handling (QUE-1)', function (object $job): void {
    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([10, 60])
        ->and($job->timeout)->toBeLessThan($job->uniqueFor)
        ->and(method_exists($job, 'failed'))->toBeTrue();
})->with([
    'task' => [new SyncBitrixTaskJob(15)],
    'project' => [new SyncBitrixProjectJob(4)],
    'task chat' => [new SyncBitrixTaskChatJob(23)],
]);

it('does not log broken business rules', function (): void {
    $handler = resolve(ExceptionHandler::class);

    expect($handler->shouldReport(new InvalidCredentialsException))->toBeFalse()
        ->and($handler->shouldReport(new ProjectHasUnfinishedTasksException))->toBeFalse();
});

it('still logs Bitrix24 failures', function (): void {
    $handler = resolve(ExceptionHandler::class);

    expect($handler->shouldReport(BitrixException::unreachable('timeout')))->toBeTrue()
        ->and($handler->shouldReport(BitrixException::fromResponse(['error' => 'ERROR_CORE'], 400)))->toBeTrue();
});
