<?php

declare(strict_types=1);

use App\Actions\Bitrix\SyncBitrixResultsAction;
use App\Models\Task;
use App\Models\TaskResult;
use App\Repositories\Contracts\TaskResultRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

it('keeps the results as Bitrix has them, with Bitrix\'s own times', function (): void {
    $task = (new Task)->forceFill(['id' => 7]);
    $results = Mockery::mock(TaskResultRepositoryInterface::class);
    $users = Mockery::mock(UserRepositoryInterface::class);
    $results->shouldReceive('bitrixResults')->andReturn(new Collection);
    $users->shouldReceive('idsByBitrixIds')->andReturn([5 => 50]);
    $saved = null;
    $results->shouldReceive('save')->once()->andReturnUsing(function (TaskResult $result) use (&$saved): void {
        $saved = $result;
    });
    $results->shouldReceive('deleteBitrixResultsExcept')->once()->with($task, [41]);

    (new SyncBitrixResultsAction($results, $users))->handle($task, [
        ['id' => 41, 'authorId' => 5, 'text' => 'Shipped', 'createdAt' => '2026-11-23T09:00:00+00:00'],
    ]);

    expect($saved)
        ->author_id->toBe(50)
        ->text->toBe('Shipped')
        ->status->toBe('open')
        ->timestamps->toBeFalse()
        // With timestamps off, Eloquent no longer casts created_at: it reads back as text.
        ->and(CarbonImmutable::parse($saved->getAttributes()['created_at'])->utc()->toIso8601String())->toBe('2026-11-23T09:00:00+00:00');
});
