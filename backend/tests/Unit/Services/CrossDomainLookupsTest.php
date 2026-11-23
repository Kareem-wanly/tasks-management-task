<?php

declare(strict_types=1);

use App\Integrations\Bitrix24\Contracts\BitrixTaskRepositoryInterface;
use App\Integrations\Bitrix24\Contracts\BitrixUserRepositoryInterface;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;
use App\Repositories\Contracts\BitrixPortalRepositoryInterface;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\TaskLookupRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\RoleServiceInterface;
use App\Services\PermissionService;
use App\Services\TaskLookupService;
use App\Services\UserService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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

it('finds users for other domains', function (): void {
    $user = (new User)->forceFill(['id' => 5]);
    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findOrFail')->once()->with(5)->andReturn($user);
    $users->shouldReceive('findMany')->once()->with([5])->andReturn(new EloquentCollection([$user]));
    $service = new UserService($users, Mockery::mock(RoleServiceInterface::class), Mockery::mock(BitrixUserRepositoryInterface::class), Mockery::mock(BitrixPortalRepositoryInterface::class), Mockery::mock(ConnectionInterface::class));

    expect($service->findOrFail(5))->toBe($user)
        ->and($service->findMany([5])->all())->toBe([$user]);
});

it('translates task statuses and priorities between our keys and Bitrix values', function (): void {
    $lookups = Mockery::mock(TaskLookupRepositoryInterface::class);
    $lookups->shouldReceive('keyForBitrix')->once()->with(TaskStatus::class, 3)->andReturn('in_progress');
    $lookups->shouldReceive('bitrixIdForKey')->once()->with(TaskPriority::class, 'urgent')->andReturn(2);
    $service = new TaskLookupService($lookups, Mockery::mock(BitrixTaskRepositoryInterface::class));

    expect($service->keyForBitrix(TaskStatus::class, 3))->toBe('in_progress')
        ->and($service->bitrixIdForKey(TaskPriority::class, 'urgent'))->toBe(2);
});
