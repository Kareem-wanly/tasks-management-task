<?php

declare(strict_types=1);

use App\DTOs\Project\AddProjectMemberDTO;
use App\DTOs\Project\UpdateProjectDTO;
use App\Exceptions\Project\ProjectHasUnfinishedTasksException;
use App\Exceptions\Project\ProjectMemberAlreadyAddedException;
use App\Exceptions\Project\ProjectMemberNotFoundException;
use App\Integrations\Bitrix24\BitrixFingerprint;
use App\Integrations\Bitrix24\Contracts\BitrixProjectRepositoryInterface;
use App\Models\Project;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\BitrixOutboundOpRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\ProjectService;
use Illuminate\Database\ConnectionInterface;

beforeEach(function (): void {
    $this->projects = Mockery::mock(ProjectRepositoryInterface::class);
    $this->bitrixProjects = Mockery::mock(BitrixProjectRepositoryInterface::class);

    $this->service = new ProjectService(
        $this->projects,
        Mockery::mock(UserRepositoryInterface::class),
        Mockery::mock(ActivityLogRepositoryInterface::class),
        $this->bitrixProjects,
        Mockery::mock(BitrixOutboundOpRepositoryInterface::class),
        resolve(BitrixFingerprint::class),
        Mockery::mock(ConnectionInterface::class),
    );
    $this->project = (new Project)->forceFill(['id' => 7, 'title' => 'Launch']);
});

it('refuses to complete a project with unfinished tasks', function (): void {
    $this->projects->shouldReceive('hasUnfinishedTasks')->once()->with($this->project)->andReturnTrue();
    $this->projects->shouldNotReceive('save');
    $this->bitrixProjects->shouldNotReceive('updateGroup');

    expect(fn () => $this->service->update(userWithRoles(), $this->project, UpdateProjectDTO::fromArray(['status' => 'completed'])))
        ->toThrow(ProjectHasUnfinishedTasksException::class);
});

it('does not check the tasks for any other status', function (): void {
    $this->projects->shouldNotReceive('hasUnfinishedTasks');
    $this->projects->shouldReceive('save')->once();
    $this->projects->shouldReceive('freshWithMembers')->once()->andReturn($this->project);

    $this->service->update(userWithRoles(), $this->project, UpdateProjectDTO::fromArray(['status' => 'on_hold']));
});

it('refuses a member who is already in the project', function (): void {
    $this->projects->shouldReceive('hasMember')->once()->with($this->project, 5)->andReturnTrue();
    $this->bitrixProjects->shouldNotReceive('addMember');

    expect(fn () => $this->service->addMember(userWithRoles(), $this->project, new AddProjectMemberDTO(userId: 5)))
        ->toThrow(ProjectMemberAlreadyAddedException::class);
});

it('refuses to remove someone who is not a member', function (): void {
    $member = userWithRoles();
    $this->projects->shouldReceive('hasMember')->once()->with($this->project, $member->id)->andReturnFalse();
    $this->bitrixProjects->shouldNotReceive('removeMember');

    expect(fn () => $this->service->removeMember(userWithRoles(), $this->project, $member))
        ->toThrow(ProjectMemberNotFoundException::class);
});
