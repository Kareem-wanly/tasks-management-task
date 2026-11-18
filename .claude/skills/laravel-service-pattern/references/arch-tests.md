# Architecture tests (Pest)

> Part of the `laravel-service-pattern` skill. The rules (ARC/SEC/CODE/QUE/CFG) live in `SKILL.md`; the templates below are the canonical shape of each file. Put this file at `tests/Arch/ArchitectureTest.php` and register the directory in `phpunit.xml` (see `testing.md`). Keep only the blocks for what the project has.

```php
<?php

declare(strict_types=1);

arch()->preset()->php();
arch()->preset()->security();

arch('strict types everywhere')
    ->expect('App')
    ->toUseStrictTypes();

// --- Shared layers ---
arch('service contracts')
    ->expect('App\Services\Contracts')
    ->toBeInterfaces()
    ->toHaveSuffix('ServiceInterface');

arch('services are final, readonly and named')
    ->expect('App\Services')
    ->classes()
    // ignoring() applies only to the expectation right before it — repeat it after each one.
    ->toBeFinal()->ignoring('App\Services\Contracts')
    ->toBeReadonly()->ignoring('App\Services\Contracts')
    ->toHaveSuffix('Service')->ignoring('App\Services\Contracts');

arch('concrete services are only referenced by bindings and tests')
    ->expect('App\Services')
    ->classes()
    ->toOnlyBeUsedIn(['App\Providers', 'Tests'])
    ->ignoring('App\Services\Contracts');

arch('services stay out of HTTP')
    ->expect('App\Services')
    ->not->toUse([
        'Illuminate\Http\Request',
        'Illuminate\Support\Facades\Request',
        'Illuminate\Http\JsonResponse',
        'Illuminate\Http\RedirectResponse',
        'Illuminate\Http\Resources\Json\JsonResource',
        'Illuminate\Contracts\View\View',
        'Inertia\Inertia',
        'Illuminate\Support\Facades\DB',
        'App\Repositories\Eloquent',
    ]);

arch('repository contracts')
    ->expect('App\Repositories\Contracts')
    ->toBeInterfaces()
    ->toHaveSuffix('RepositoryInterface');

arch('eloquent repositories')
    ->expect('App\Repositories\Eloquent')
    ->toBeFinal()
    ->toHavePrefix('Eloquent')
    ->toHaveSuffix('Repository')
    ->toOnlyBeUsedIn(['App\Providers', 'Tests'])
    ->not->toUse(['App\Services', 'App\Jobs', 'Illuminate\Support\Facades\Event']);

arch('dtos are immutable')
    ->expect('App\DTOs')
    ->toBeFinal()
    ->toBeReadonly()
    ->toHaveSuffix('DTO');

arch('form requests')
    ->expect('App\Http\Requests')
    ->toBeFinal()->ignoring('App\Http\Requests\Bitrix\BitrixRequest') // abstract base requests only
    ->toHaveSuffix('Request');

arch('domain exceptions')
    ->expect('App\Exceptions')
    ->classes()
    ->toHaveSuffix('Exception')
    ->toExtend('App\Exceptions\BusinessException')
    ->ignoring('App\Exceptions\BusinessException');

arch('controllers never query or dispatch')
    ->expect('App\Http\Controllers')
    ->not->toUse(['Illuminate\Support\Facades\DB', 'App\Repositories', 'App\Jobs', 'App\Actions']);

// --- API mode ---
arch('api controllers return JSON only')
    ->expect('App\Http\Controllers\Api')
    ->toBeFinal()
    ->not->toUse(['Illuminate\Contracts\View\View', 'Inertia\Inertia', 'Illuminate\Http\RedirectResponse']);

arch('resources')
    ->expect('App\Http\Resources')
    ->toBeFinal()
    ->toHaveSuffix('Resource');

// --- Web mode (Blade / Inertia) ---
arch('web controllers never return raw JSON')
    ->expect('App\Http\Controllers\Web')
    ->toBeFinal()
    ->not->toUse('Illuminate\Http\JsonResponse');

// --- Actions, jobs, listeners, commands ---
arch('actions')
    ->expect('App\Actions')
    ->toBeFinal()
    ->toBeReadonly()
    ->toHaveSuffix('Action')
    ->toHaveMethod('handle')
    ->toOnlyBeUsedIn(['App\Services', 'Tests'])
    ->not->toUse([
        'Illuminate\Http\Request',
        'Illuminate\Support\Facades\DB',
        'Illuminate\Database\ConnectionInterface',
        'App\Jobs',
    ]);

arch('jobs are thin and queued')
    ->expect('App\Jobs')
    ->toBeFinal()
    ->toHaveSuffix('Job')
    ->toImplement('Illuminate\Contracts\Queue\ShouldQueue')
    ->toOnlyBeUsedIn(['App\Services', 'Tests'])
    ->not->toUse(['App\Repositories', 'Illuminate\Support\Facades\DB']);

arch('listeners are thin')
    ->expect('App\Listeners')
    ->toBeFinal()
    ->toHaveSuffix('Listener')
    ->not->toUse(['App\Repositories', 'Illuminate\Support\Facades\DB']);

arch('commands are thin')
    ->expect('App\Console\Commands')
    ->toBeFinal()
    ->toHaveSuffix('Command')
    ->not->toUse(['App\Repositories', 'Illuminate\Support\Facades\DB']);

// --- External API data source ---
arch('only integrations talk to external HTTP')
    ->expect(['Illuminate\Support\Facades\Http', 'Illuminate\Http\Client\Factory'])
    ->toOnlyBeUsedIn(['App\Integrations', 'Tests']);

arch('integrations are final')
    ->expect('App\Integrations')
    ->classes()
    ->toBeFinal();

arch('gateway contracts')
    ->expect('App\Integrations')
    ->interfaces()
    ->toHaveSuffix('GatewayInterface');

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
```
