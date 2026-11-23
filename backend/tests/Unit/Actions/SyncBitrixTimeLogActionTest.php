<?php

declare(strict_types=1);

use App\Actions\Bitrix\SyncBitrixTimeLogAction;
use App\Models\Task;
use App\Models\TaskElapsedItem;
use App\Repositories\Contracts\TimeEntryRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

it('keeps the log as Bitrix has it and updates the task total', function (): void {
    $task = (new Task)->forceFill(['id' => 7]);
    $entries = Mockery::mock(TimeEntryRepositoryInterface::class);
    $users = Mockery::mock(UserRepositoryInterface::class);
    $entries->shouldReceive('bitrixEntries')->andReturn(new Collection);
    $users->shouldReceive('idsByBitrixIds')->with([5, 99])->andReturn([5 => 50]);
    $saved = [];
    $entries->shouldReceive('save')->andReturnUsing(function (TaskElapsedItem $item) use (&$saved): void {
        $saved[] = $item;
    });
    $entries->shouldReceive('deleteBitrixEntriesExcept')->once()->with($task, [11, 12]);
    $entries->shouldReceive('updateTaskTotal')->once()->with($task);

    (new SyncBitrixTimeLogAction($entries, $users))->handle($task, [
        ['ID' => '11', 'USER_ID' => '5', 'SECONDS' => '600', 'COMMENT_TEXT' => 'Review', 'CREATED_DATE' => '2026-11-23T09:00:00+00:00'],
        // Someone we haven't imported: the entry is kept without a user.
        ['ID' => '12', 'USER_ID' => '99', 'SECONDS' => '60', 'COMMENT_TEXT' => ''],
    ]);

    expect($saved[0])->user_id->toBe(50)->seconds->toBe(600)->comment->toBe('Review')
        ->and($saved[1])->user_id->toBeNull()->comment->toBeNull()->created_date->toBeNull();
});
