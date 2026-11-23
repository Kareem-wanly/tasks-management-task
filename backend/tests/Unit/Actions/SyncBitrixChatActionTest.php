<?php

declare(strict_types=1);

use App\Actions\Bitrix\SyncBitrixChatAction;
use App\Models\Task;
use App\Models\TaskChatMessage;
use App\Repositories\Contracts\TaskChatMessageRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

it('saves messages oldest first, skips deleted ones and marks system lines', function (): void {
    $task = (new Task)->forceFill(['id' => 7]);
    $messages = Mockery::mock(TaskChatMessageRepositoryInterface::class);
    $users = Mockery::mock(UserRepositoryInterface::class);
    $messages->shouldReceive('bitrixMessages')->with($task, null)->andReturn(new Collection);
    $users->shouldReceive('idsByBitrixIds')->andReturn([5 => 50]);
    $saved = [];
    $messages->shouldReceive('save')->andReturnUsing(function (TaskChatMessage $message) use (&$saved): void {
        $saved[] = $message;
    });
    // A complete chat: whatever Bitrix no longer has (the deleted message too) goes.
    $messages->shouldReceive('deleteBitrixMessagesExcept')->once()->with($task, [301, 302], null);

    (new SyncBitrixChatAction($messages, $users))->handle($task, [
        ['id' => 303, 'author_id' => 5, 'text' => 'gone', 'params' => ['IS_DELETED' => 'Y']],
        ['id' => 302, 'author_id' => 0, 'text' => 'Task status changed', 'params' => ['FILE_ID' => [9]]],
        ['id' => 301, 'author_id' => 5, 'text' => 'On it'],
    ], [['id' => 9, 'name' => 'log.txt', 'size' => 12, 'type' => 'file']], complete: true);

    expect($saved)->toHaveCount(2)
        ->and($saved[0])->bitrix_message_id->toBe(301)->author_id->toBe(50)->is_system->toBeFalse()
        ->and($saved[1])->is_system->toBeTrue()
        ->and($saved[1]->files)->toBe([['id' => 9, 'name' => 'log.txt', 'size' => 12, 'type' => 'file']]);
});
