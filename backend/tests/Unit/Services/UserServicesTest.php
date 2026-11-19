<?php

declare(strict_types=1);

use App\DTOs\User\SetUserActiveDTO;
use App\DTOs\User\SyncUserRolesDTO;
use App\Enums\SystemRole;
use App\Exceptions\User\AdministratorChangeDeniedException;
use App\Exceptions\User\CannotDeactivateOwnAccountException;
use App\Exceptions\User\LastAdministratorException;
use App\Integrations\Bitrix24\Contracts\BitrixUserRepositoryInterface;
use App\Models\Role;
use App\Repositories\Contracts\BitrixPortalRepositoryInterface;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\UserRoleService;
use App\Services\UserService;
use Illuminate\Database\ConnectionInterface;

beforeEach(function (): void {
    $this->users = Mockery::mock(UserRepositoryInterface::class);
    $this->roles = Mockery::mock(RoleRepositoryInterface::class);
    $this->bitrixUsers = Mockery::mock(BitrixUserRepositoryInterface::class);
    $this->database = Mockery::mock(ConnectionInterface::class);
    $this->database->shouldReceive('transaction')->andReturnUsing(fn (Closure $callback): mixed => $callback());
});

describe('UserService::setActive', function (): void {
    beforeEach(function (): void {
        $this->service = new UserService($this->users, $this->roles, $this->bitrixUsers, Mockery::mock(BitrixPortalRepositoryInterface::class), $this->database);
    });

    it('never deactivates your own account', function (): void {
        $admin = userWithRoles(SystemRole::Administrator->value);
        $this->bitrixUsers->shouldNotReceive('setActive');
        $this->users->shouldNotReceive('save');

        expect(fn () => $this->service->setActive($admin, $admin, new SetUserActiveDTO(false)))
            ->toThrow(CannotDeactivateOwnAccountException::class);
    });

    it('lets only an administrator change an administrator', function (): void {
        $this->bitrixUsers->shouldNotReceive('setActive');

        expect(fn () => $this->service->setActive(userWithRoles('Project Manager'), userWithRoles(SystemRole::Administrator->value), new SetUserActiveDTO(false)))
            ->toThrow(AdministratorChangeDeniedException::class);
    });
});

describe('UserRoleService::sync', function (): void {
    beforeEach(function (): void {
        $this->service = new UserRoleService($this->users, $this->roles, Mockery::mock(PermissionRepositoryInterface::class), $this->database);
        $this->adminRole = (new Role)->forceFill(['id' => 1, 'name' => SystemRole::Administrator->value]);
        $this->roles->shouldReceive('findByName')->with(SystemRole::Administrator->value)->andReturn($this->adminRole);
    });

    it('keeps the last administrator', function (): void {
        $lastAdmin = userWithRoles(SystemRole::Administrator->value);
        $this->users->shouldReceive('hasRoleId')->with($lastAdmin, 1)->andReturnTrue();
        $this->users->shouldReceive('countWithRoleForUpdate')->with($this->adminRole)->andReturn(1);
        $this->users->shouldNotReceive('syncRoles');

        expect(fn () => $this->service->sync(userWithRoles(SystemRole::Administrator->value), $lastAdmin, new SyncUserRolesDTO([2])))
            ->toThrow(LastAdministratorException::class);
    });

    it('takes the role from an administrator while another one is left', function (): void {
        $admin = userWithRoles(SystemRole::Administrator->value);
        $this->users->shouldReceive('hasRoleId')->with($admin, 1)->andReturnTrue();
        $this->users->shouldReceive('countWithRoleForUpdate')->andReturn(2);
        $this->users->shouldReceive('syncRoles')->once()->with($admin, [2]);
        $this->users->shouldReceive('withRoles')->once()->andReturn($admin);

        $this->service->sync(userWithRoles(SystemRole::Administrator->value), $admin, new SyncUserRolesDTO([2]));
    });
});
