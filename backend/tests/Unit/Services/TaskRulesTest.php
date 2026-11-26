<?php

declare(strict_types=1);

use App\DTOs\Task\MoveChecklistItemDTO;
use App\DTOs\Task\StopTimerDTO;
use App\Exceptions\Task\ChecklistListNotMovableException;
use App\Exceptions\Task\TimerRunningOnAnotherTaskException;
use App\Exceptions\TaskLookup\LookupFromBitrixException;
use App\Exceptions\TaskLookup\LookupInUseException;
use App\Integrations\Bitrix24\BitrixChecklistSync;
use App\Integrations\Bitrix24\BitrixTimeSync;
use App\Integrations\Bitrix24\Contracts\BitrixChecklistRepositoryInterface;
use App\Integrations\Bitrix24\Contracts\BitrixTaskRepositoryInterface;
use App\Integrations\Bitrix24\Contracts\BitrixTimeRepositoryInterface;
use App\Models\RunningTimer;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\TaskStatus;
use App\Repositories\Contracts\TaskChecklistRepositoryInterface;
use App\Repositories\Contracts\TaskLookupRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\TimeEntryRepositoryInterface;
use App\Services\TaskChecklistService;
use App\Services\TaskLookupService;
use App\Services\TaskTimeService;
use Illuminate\Database\ConnectionInterface;

describe('TaskLookupService::delete', function (): void {
    beforeEach(function (): void {
        $this->lookups = Mockery::mock(TaskLookupRepositoryInterface::class);
        $this->service = new TaskLookupService($this->lookups, Mockery::mock(BitrixTaskRepositoryInterface::class));
    });

    it('keeps a value that comes from Bitrix24', function (): void {
        $this->lookups->shouldNotReceive('delete');

        expect(fn () => $this->service->delete((new TaskStatus)->forceFill(['is_system' => true])))
            ->toThrow(LookupFromBitrixException::class);
    });

    it('keeps a value tasks still use', function (): void {
        $status = (new TaskStatus)->forceFill(['is_system' => false]);
        $this->lookups->shouldReceive('isUsed')->once()->with($status)->andReturnTrue();
        $this->lookups->shouldNotReceive('delete');

        expect(fn () => $this->service->delete($status))->toThrow(LookupInUseException::class);
    });

    it('deletes an unused value of ours', function (): void {
        $status = (new TaskStatus)->forceFill(['is_system' => false]);
        $this->lookups->shouldReceive('isUsed')->andReturnFalse();
        $this->lookups->shouldReceive('delete')->once()->with($status);

        $this->service->delete($status);
    });
});

it('never moves a checklist list, only its items', function (): void {
    $checklists = Mockery::mock(TaskChecklistRepositoryInterface::class);
    $bitrixChecklists = Mockery::mock(BitrixChecklistRepositoryInterface::class);
    $bitrixChecklists->shouldNotReceive('move');
    $service = new TaskChecklistService($checklists, $bitrixChecklists, resolve(BitrixChecklistSync::class), Mockery::mock(ConnectionInterface::class));

    expect(fn (): App\DTOs\Task\TaskChecklistDTO => $service->move(userWithRoles(), (new TaskChecklistItem)->forceFill(['parent_id' => null]), new MoveChecklistItemDTO(null)))
        ->toThrow(ChecklistListNotMovableException::class);
});

it('stops a timer only on the task it runs on', function (): void {
    $entries = Mockery::mock(TimeEntryRepositoryInterface::class);
    $entries->shouldReceive('runningTimer')->andReturn((new RunningTimer)->forceFill(['task_id' => 99]));
    $entries->shouldNotReceive('create');
    $service = new TaskTimeService($entries, Mockery::mock(TaskRepositoryInterface::class), Mockery::mock(BitrixTimeRepositoryInterface::class), resolve(BitrixTimeSync::class));

    expect(fn (): App\DTOs\Task\TimeLogDTO => $service->stopTimer(userWithRoles(), (new Task)->forceFill(['id' => 1]), new StopTimerDTO))
        ->toThrow(TimerRunningOnAnotherTaskException::class);
});
