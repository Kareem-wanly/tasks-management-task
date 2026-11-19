<?php

declare(strict_types=1);

use App\DTOs\Role\SyncRolePermissionsDTO;
use App\DTOs\Role\UpdateRoleDTO;
use App\Enums\SystemRole;
use App\Exceptions\Role\ReservedRoleNameException;
use App\Exceptions\Role\RolePermissionsChangeDeniedException;
use App\Exceptions\Role\SystemRoleCannotBeDeletedException;
use App\Exceptions\Role\SystemRoleCannotBeRenamedException;
use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Services\RoleService;
use Illuminate\Database\ConnectionInterface;

beforeEach(function (): void {
    $this->roles = Mockery::mock(RoleRepositoryInterface::class);
    $this->service = new RoleService($this->roles, Mockery::mock(ConnectionInterface::class));
    $this->admin = userWithRoles(SystemRole::Administrator->value);
});

it('keeps the names of system roles', function (string $name): void {
    $this->roles->shouldNotReceive('update');

    expect(fn () => $this->service->update($this->admin, (new Role)->forceFill(['name' => $name]), UpdateRoleDTO::fromArray(['name' => 'Renamed'])))
        ->toThrow(SystemRoleCannotBeRenamedException::class);
})->with(SystemRole::names());

it('does not let another role take a system role name', function (): void {
    $this->roles->shouldNotReceive('update');

    expect(fn () => $this->service->update($this->admin, (new Role)->forceFill(['name' => 'Editors']), UpdateRoleDTO::fromArray(['name' => 'Member'])))
        ->toThrow(ReservedRoleNameException::class);
});

it('never deletes a system role', function (): void {
    $this->roles->shouldNotReceive('delete');

    expect(fn () => $this->service->delete((new Role)->forceFill(['name' => SystemRole::Member->value])))
        ->toThrow(SystemRoleCannotBeDeletedException::class, 'Protected system role [Member] cannot be deleted');
});

it('deletes any other role', function (): void {
    $role = (new Role)->forceFill(['name' => 'Editors']);
    $this->roles->shouldReceive('delete')->once()->with($role);

    $this->service->delete($role);
});

it('lets only an administrator change the Administrator role', function (): void {
    $this->roles->shouldNotReceive('syncPermissions');

    expect(fn () => $this->service->syncPermissions(userWithRoles('Project Manager'), (new Role)->forceFill(['name' => SystemRole::Administrator->value]), new SyncRolePermissionsDTO([1])))
        ->toThrow(RolePermissionsChangeDeniedException::class);
});
