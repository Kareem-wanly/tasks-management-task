<?php

namespace App\Providers;

use App\Repositories\Contracts;
use App\Repositories\Eloquent;
use Illuminate\Support\ServiceProvider;

/**
 * Binds every repository interface to its Eloquent implementation. Services
 * depend on the interfaces only.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        Contracts\BitrixPortalRepositoryInterface::class => Eloquent\BitrixPortalRepository::class,
        Contracts\ActivityLogRepositoryInterface::class => Eloquent\ActivityLogRepository::class,
        Contracts\CommentRepositoryInterface::class => Eloquent\CommentRepository::class,
        Contracts\ProjectRepositoryInterface::class => Eloquent\ProjectRepository::class,
        Contracts\PermissionRepositoryInterface::class => Eloquent\PermissionRepository::class,
        Contracts\RoleRepositoryInterface::class => Eloquent\RoleRepository::class,
        Contracts\TaskChecklistRepositoryInterface::class => Eloquent\TaskChecklistRepository::class,
        Contracts\TaskLookupRepositoryInterface::class => Eloquent\TaskLookupRepository::class,
        Contracts\TaskRepositoryInterface::class => Eloquent\TaskRepository::class,
        Contracts\TimeEntryRepositoryInterface::class => Eloquent\TimeEntryRepository::class,
        Contracts\UserRepositoryInterface::class => Eloquent\UserRepository::class,
    ];
}
