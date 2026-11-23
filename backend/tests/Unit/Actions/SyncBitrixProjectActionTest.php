<?php

declare(strict_types=1);

use App\Actions\Bitrix\SyncBitrixProjectAction;
use App\Enums\ProjectStatus;
use App\Integrations\Bitrix24\Mappers\BitrixProjectMapper;
use App\Models\Project;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;

beforeEach(function (): void {
    $this->projects = Mockery::mock(ProjectRepositoryInterface::class);
    $this->users = Mockery::mock(UserRepositoryInterface::class);
    $this->action = new SyncBitrixProjectAction(new BitrixProjectMapper, $this->projects, $this->users);
    $this->group = ['id' => 12, 'name' => 'Launch', 'ownerId' => 5, 'closed' => 'N'];
});

it('skips a group whose owner is not imported', function (): void {
    $this->users->shouldReceive('idByBitrixId')->with(5)->andReturnNull();
    $this->projects->shouldNotReceive('save');

    expect($this->action->handle($this->group, []))->toBeNull();
});

it('leaves a project deleted here when its group is closed in Bitrix (our own delete)', function (): void {
    $deleted = (new Project)->forceFill(['id' => 3, 'bitrix_group_id' => 12, 'deleted_at' => now()]);
    $this->users->shouldReceive('idByBitrixId')->andReturn(50);
    $this->projects->shouldReceive('findByBitrixGroupId')->with(12, true)->andReturn($deleted);
    $this->projects->shouldNotReceive('restore');
    $this->projects->shouldNotReceive('save');

    expect($this->action->handle([...$this->group, 'closed' => 'Y'], []))->toBe($deleted);
});

it('creates a new project with its owner and the members we know', function (): void {
    $this->users->shouldReceive('idByBitrixId')->with(5)->andReturn(50);
    $this->users->shouldReceive('idsByBitrixIds')->with([5, 6, 7])->andReturn([5 => 50, 6 => 60]);
    $this->projects->shouldReceive('findByBitrixGroupId')->andReturnNull();
    $saved = null;
    $this->projects->shouldReceive('save')->once()->andReturnUsing(function (Project $project) use (&$saved): void {
        $saved = $project;
    });
    $this->projects->shouldReceive('markSynced')->once();
    $this->projects->shouldReceive('syncMembersWithRoles')->once()
        ->with(Mockery::type(Project::class), [50 => ['role' => 'owner'], 60 => ['role' => 'moderator']]);

    $this->action->handle($this->group, [
        ['USER_ID' => '5', 'ROLE' => 'A'],
        ['USER_ID' => '6', 'ROLE' => 'E'],
        ['USER_ID' => '7', 'ROLE' => 'K'], // not imported: left out
    ]);

    expect($saved)->title->toBe('Launch')->owner_id->toBe(50)->status->toBe(ProjectStatus::Active);
});
