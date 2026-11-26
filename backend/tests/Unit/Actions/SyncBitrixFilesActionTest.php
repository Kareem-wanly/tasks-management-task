<?php

declare(strict_types=1);

use App\Actions\Bitrix\SyncBitrixFilesAction;
use App\Models\Task;
use App\Models\TaskFile;
use App\Repositories\Contracts\TaskFileRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

it('lists the files attached in Bitrix and drops the ones taken off', function (): void {
    $task = (new Task)->forceFill(['id' => 7]);
    $files = Mockery::mock(TaskFileRepositoryInterface::class);
    $users = Mockery::mock(UserRepositoryInterface::class);
    $files->shouldReceive('bitrixFiles')->andReturn(new Collection);
    $users->shouldReceive('idsByBitrixIds')->andReturn([5 => 50]);
    $saved = null;
    $files->shouldReceive('save')->once()->andReturnUsing(function (TaskFile $file) use (&$saved): void {
        $saved = $file;
    });
    $files->shouldReceive('deleteBitrixFilesExcept')->once()->with($task, [31]);

    (new SyncBitrixFilesAction($files, $users))->handle($task, [
        ['ID' => '31', 'OBJECT_ID' => '900', 'NAME' => 'plan.pdf', 'SIZE' => '2048', 'CREATED_BY' => '5'],
    ]);

    expect($saved)
        ->attached_object_id->toBe(31)
        ->bitrix_disk_file_id->toBe(900)
        ->uploaded_by->toBe(50)
        ->size->toBe(2048)
        ->mime->toBe('application/pdf');
});
