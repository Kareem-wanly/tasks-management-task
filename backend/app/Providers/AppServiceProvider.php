<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\SystemRole;
use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;
use App\Observers\ProjectObserver;
use App\Observers\TaskObserver;
use App\Policies\TaskLookupPolicy;
use App\Repositories\Contracts\BitrixPortalRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            BitrixGatewayInterface::SERVICE_ACCOUNT,
            fn ($app) => $app->make(BitrixPortalRepositoryInterface::class)->current() ?? throw BitrixException::missingToken()
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Date::use(CarbonImmutable::class);
        Model::shouldBeStrict(! $this->app->isProduction());
        DB::prohibitDestructiveCommands($this->app->isProduction());

        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole(SystemRole::Administrator)) {
                return true;
            }

        });

        Gate::policy(TaskStatus::class, TaskLookupPolicy::class);
        Gate::policy(TaskPriority::class, TaskLookupPolicy::class);

        Project::observe(ProjectObserver::class);
        Task::observe(TaskObserver::class);

    }
}
