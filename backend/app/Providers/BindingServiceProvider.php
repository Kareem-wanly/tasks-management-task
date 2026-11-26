<?php

declare(strict_types=1);

namespace App\Providers;

use App\Integrations\Bitrix24 as Bitrix;
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
        Bitrix\Contracts\BitrixOAuthGatewayInterface::class => Bitrix\BitrixOAuthGateway::class,
        Bitrix\Contracts\TokenManagerInterface::class => Bitrix\TokenManager::class,
        Bitrix\Contracts\BitrixServiceAccountInterface::class => Bitrix\PortalServiceAccount::class,
        Bitrix\Contracts\BitrixTaskResolverInterface::class => Bitrix\BitrixTaskResolver::class,
        Bitrix\Contracts\BitrixEchoGuardInterface::class => Bitrix\BitrixEchoGuard::class,
        Bitrix\Contracts\BitrixDeletionGuardInterface::class => Bitrix\BitrixDeletionGuard::class,
        Bitrix\Contracts\BitrixLoginCodesInterface::class => Bitrix\BitrixLoginCodes::class,
        Bitrix\Contracts\BitrixRecordFetcherInterface::class => Bitrix\BitrixRecordFetcher::class,
        Bitrix\Contracts\BitrixTaskMapperInterface::class => Bitrix\Mappers\BitrixTaskMapper::class,
        Bitrix\Contracts\BitrixProjectMapperInterface::class => Bitrix\Mappers\BitrixProjectMapper::class,
        Bitrix\Contracts\BitrixHistoryMapperInterface::class => Bitrix\Mappers\BitrixHistoryMapper::class,
        Bitrix\Contracts\BitrixAppRepositoryInterface::class => Bitrix\Repositories\BitrixAppRepository::class,
        Bitrix\Contracts\BitrixProjectRepositoryInterface::class => Bitrix\Repositories\BitrixProjectRepository::class,
        Bitrix\Contracts\BitrixUserRepositoryInterface::class => Bitrix\Repositories\BitrixUserRepository::class,
        Bitrix\Contracts\BitrixTaskRepositoryInterface::class => Bitrix\Repositories\BitrixTaskRepository::class,
        Bitrix\Contracts\BitrixChatRepositoryInterface::class => Bitrix\Repositories\BitrixChatRepository::class,
        Bitrix\Contracts\BitrixChecklistRepositoryInterface::class => Bitrix\Repositories\BitrixChecklistRepository::class,
        Bitrix\Contracts\BitrixFileRepositoryInterface::class => Bitrix\Repositories\BitrixFileRepository::class,
        Bitrix\Contracts\BitrixResultRepositoryInterface::class => Bitrix\Repositories\BitrixResultRepository::class,
        Bitrix\Contracts\BitrixTimeRepositoryInterface::class => Bitrix\Repositories\BitrixTimeRepository::class,

        // Repositories
        RepositoryContracts\ActivityLogRepositoryInterface::class => Eloquent\EloquentActivityLogRepository::class,
        RepositoryContracts\BitrixOutboundOpRepositoryInterface::class => Eloquent\EloquentBitrixOutboundOpRepository::class,
        RepositoryContracts\BitrixPortalRepositoryInterface::class => Eloquent\EloquentBitrixPortalRepository::class,
        RepositoryContracts\BitrixReconciliationRepositoryInterface::class => Eloquent\EloquentBitrixReconciliationRepository::class,
        RepositoryContracts\BitrixSyncCursorRepositoryInterface::class => Eloquent\EloquentBitrixSyncCursorRepository::class,
        RepositoryContracts\BitrixSyncFailureRepositoryInterface::class => Eloquent\EloquentBitrixSyncFailureRepository::class,
        RepositoryContracts\BitrixTokenRepositoryInterface::class => Eloquent\EloquentBitrixTokenRepository::class,
        RepositoryContracts\FailedJobRepositoryInterface::class => Eloquent\EloquentFailedJobRepository::class,
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
        ServiceContracts\BitrixSyncServiceInterface::class => Services\BitrixSyncService::class,
        ServiceContracts\BitrixSyncStatusServiceInterface::class => Services\BitrixSyncStatusService::class,
        ServiceContracts\BitrixSyncFailureServiceInterface::class => Services\BitrixSyncFailureService::class,
        ServiceContracts\BitrixReconciliationServiceInterface::class => Services\BitrixReconciliationService::class,
        ServiceContracts\BitrixImportServiceInterface::class => Services\BitrixImportService::class,
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
