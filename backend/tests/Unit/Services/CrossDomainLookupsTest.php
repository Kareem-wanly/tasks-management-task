<?php

declare(strict_types=1);

use App\Integrations\Bitrix24\Contracts\BitrixUserRepositoryInterface;
use App\Models\User;
use App\Repositories\Contracts\BitrixPortalRepositoryInterface;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\RoleServiceInterface;
use App\Services\PermissionService;
use App\Services\UserService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

/*
 * C.2 (ARC-9): what other domains ask the permission and user services for,
 * instead of reaching into their repositories.
 */

it('tells which permissions a set of roles grants', function (): void {
    $permissions = Mockery::mock(PermissionRepositoryInterface::class);
    $permissions->shouldReceive('idsGrantedByRoles')->once()->with([2, 3])->andReturn(new Collection([4, 7]));

    expect((new PermissionService($permissions))->idsGrantedByRoles([2, 3])->all())->toBe([4, 7]);
});

it('issues an API token for a user', function (): void {
    $user = new User;
    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('createApiToken')->once()->with($user, 'bitrix24')->andReturn('1|plain');
    $service = new UserService($users, Mockery::mock(RoleServiceInterface::class), Mockery::mock(BitrixUserRepositoryInterface::class), Mockery::mock(BitrixPortalRepositoryInterface::class), Mockery::mock(ConnectionInterface::class));

    expect($service->issueApiToken($user, 'bitrix24'))->toBe('1|plain');
});
