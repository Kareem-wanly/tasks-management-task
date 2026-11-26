<?php

declare(strict_types=1);

namespace App\Providers;

use App\Integrations\Bitrix24\BitrixGateway;
use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Repositories\Contracts as RepositoryContracts;
use App\Repositories\Eloquent;
use App\Services;
use App\Services\Contracts as ServiceContracts;
use Illuminate\Support\ServiceProvider;

/**
 * The only place interfaces meet their implementations (CFG-2): controllers
 * depend on service interfaces, services on repository interfaces.
 */
final class BindingServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        // Integrations
        BitrixGatewayInterface::class => BitrixGateway::class,

        // Repositories
        RepositoryContracts\ActivityLogRepositoryInterface::class => Eloquent\EloquentActivityLogRepository::class,
        RepositoryContracts\BitrixOutboundOpRepositoryInterface::class => Eloquent\EloquentBitrixOutboundOpRepository::class,
        RepositoryContracts\BitrixPortalRepositoryInterface::class => Eloquent\EloquentBitrixPortalRepository::class,
        RepositoryContracts\BitrixSyncCursorRepositoryInterface::class => Eloquent\EloquentBitrixSyncCursorRepository::class,
        RepositoryContracts\PermissionRepositoryInterface::class => Eloquent\EloquentPermissionRepository::class,
        RepositoryContracts\ProjectRepositoryInterface::class => Eloquent\EloquentProjectRepository::class,
        RepositoryContracts\RoleRepositoryInterface::class => Eloquent\EloquentRoleRepository::class,
        RepositoryContracts\TaskChatMessageRepositoryInterface::class => Eloquent\EloquentTaskChatMessageRepository::class,
        RepositoryContracts\TaskChecklistRepositoryInterface::class => Eloquent\EloquentTaskChecklistRepository::class,
        RepositoryContracts\TaskFileRepositoryInterface::class => Eloquent\EloquentTaskFileRepository::class,
        RepositoryContracts\TaskLookupRepositoryInterface::class => Eloquent\EloquentTaskLookupRepository::class,
        RepositoryContracts\TaskRepositoryInterface::class => Eloquent\EloquentTaskRepository::class,
        RepositoryContracts\TaskResultRepositoryInterface::class => Eloquent\EloquentTaskResultRepository::class,
        RepositoryContracts\TimeEntryRepositoryInterface::class => Eloquent\EloquentTimeEntryRepository::class,
        RepositoryContracts\UserRepositoryInterface::class => Eloquent\EloquentUserRepository::class,

        // Services
        ServiceContracts\ActivityLogServiceInterface::class => Services\ActivityLogService::class,
        ServiceContracts\AuthServiceInterface::class => Services\AuthService::class,
        ServiceContracts\BitrixAuthServiceInterface::class => Services\BitrixAuthService::class,
        ServiceContracts\BitrixEventServiceInterface::class => Services\BitrixEventService::class,
        ServiceContracts\BitrixInstallServiceInterface::class => Services\BitrixInstallService::class,
        ServiceContracts\PermissionServiceInterface::class => Services\PermissionService::class,
        ServiceContracts\ProjectServiceInterface::class => Services\ProjectService::class,
        ServiceContracts\RoleServiceInterface::class => Services\RoleService::class,
        ServiceContracts\TaskChatServiceInterface::class => Services\TaskChatService::class,
        ServiceContracts\TaskChecklistServiceInterface::class => Services\TaskChecklistService::class,
        ServiceContracts\TaskFileServiceInterface::class => Services\TaskFileService::class,
        ServiceContracts\TaskHistoryServiceInterface::class => Services\TaskHistoryService::class,
        ServiceContracts\TaskLookupServiceInterface::class => Services\TaskLookupService::class,
        ServiceContracts\TaskMemberServiceInterface::class => Services\TaskMemberService::class,
        ServiceContracts\TaskResultServiceInterface::class => Services\TaskResultService::class,
        ServiceContracts\TaskServiceInterface::class => Services\TaskService::class,
        ServiceContracts\TaskTimeServiceInterface::class => Services\TaskTimeService::class,
        ServiceContracts\UserRoleServiceInterface::class => Services\UserRoleService::class,
        ServiceContracts\UserServiceInterface::class => Services\UserService::class,
    ];
}
