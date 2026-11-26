<?php

declare(strict_types=1);

use App\Actions\Bitrix\SyncBitrixChecklistAction;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Repositories\Contracts\TaskChecklistRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

it('saves every item, links each to its list and drops the ones Bitrix no longer has', function (): void {
    $task = (new Task)->forceFill(['id' => 7]);
    $checklists = Mockery::mock(TaskChecklistRepositoryInterface::class);
    $checklists->shouldReceive('bitrixItems')->with($task)->andReturn(new Collection);
    $nextId = 100;
    $saved = [];
    $checklists->shouldReceive('save')->andReturnUsing(function (TaskChecklistItem $item) use (&$nextId, &$saved): void {
        $item->id ??= $nextId++;
        $item->syncOriginal();
        $saved[$item->bitrix_item_id] = $item;
    });
    $checklists->shouldReceive('deleteBitrixItemsExcept')->once()->with($task, [2, 1]);

    // The item comes before its list: links are made after every row is saved.
    (new SyncBitrixChecklistAction($checklists))->handle($task, [
        ['ID' => '2', 'PARENT_ID' => '1', 'TITLE' => 'Write tests', 'IS_COMPLETE' => 'Y', 'SORT_INDEX' => '0'],
        ['ID' => '1', 'PARENT_ID' => '0', 'TITLE' => 'Release', 'IS_COMPLETE' => 'N', 'SORT_INDEX' => '0'],
    ]);

    expect($saved[2])->title->toBe('Write tests')->is_complete->toBeTrue()->parent_id->toBe($saved[1]->id)
        ->and($saved[1])->parent_id->toBeNull()->task_id->toBe(7);
});
