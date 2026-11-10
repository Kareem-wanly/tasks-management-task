<?php

namespace App\Providers;

use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixException;
use App\Models\BitrixPortal;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;
use App\Observers\ProjectObserver;
use App\Observers\TaskObserver;
use App\Policies\TaskLookupPolicy;
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
            BitrixClient::SERVICE_ACCOUNT,
            fn () => BitrixPortal::current() ?? throw BitrixException::missingToken()
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('Administrator')) {
                return true;
            }

        });

        Gate::policy(TaskStatus::class, TaskLookupPolicy::class);
        Gate::policy(TaskPriority::class, TaskLookupPolicy::class);

        Project::observe(ProjectObserver::class);
        Task::observe(TaskObserver::class);

    }
}
