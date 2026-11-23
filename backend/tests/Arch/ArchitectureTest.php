<?php

declare(strict_types=1);

/*
 * The laravel-service-pattern rules as tests (PR9): a change that breaks one
 * fails `composer check`, so the architecture can't drift back.
 */

arch()->preset()->php();
arch()->preset()->security();

arch('strict types everywhere')
    ->expect('App')
    ->toUseStrictTypes();

// --- Services ---
arch('service contracts')
    ->expect('App\Services\Contracts')
    ->toBeInterfaces()
    ->toHaveSuffix('ServiceInterface');

arch('services are final, readonly and named')
    ->expect('App\Services')
    ->classes()
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
        'Illuminate\Support\Facades\DB',
        'App\Repositories\Eloquent',
    ]);

// --- Repositories ---
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

// --- DTOs, requests, exceptions ---
arch('dtos are immutable')
    ->expect('App\DTOs')
    ->classes()
    // ignoring() applies only to the expectation right before it; Concerns holds traits.
    ->toBeFinal()->ignoring('App\DTOs\Concerns')
    ->toBeReadonly()->ignoring('App\DTOs\Concerns')
    ->toHaveSuffix('DTO')->ignoring('App\DTOs\Concerns');

arch('form requests')
    ->expect('App\Http\Requests')
    ->classes()
    // TaskLookupRequest is the abstract base of the status and priority requests.
    ->toBeFinal()->ignoring(['App\Http\Requests\TaskLookupRequest', 'App\Http\Requests\Concerns'])
    ->toHaveSuffix('Request')->ignoring('App\Http\Requests\Concerns');

arch('domain exceptions')
    ->expect('App\Exceptions')
    ->classes()
    ->toHaveSuffix('Exception')
    ->toExtend('App\Exceptions\BusinessException')
    ->ignoring('App\Exceptions\BusinessException');

// --- HTTP entry points ---
arch('controllers never query or dispatch')
    ->expect('App\Http\Controllers')
    ->not->toUse(['Illuminate\Support\Facades\DB', 'App\Repositories', 'App\Jobs', 'App\Integrations']);

arch('controllers are final')
    ->expect('App\Http\Controllers')
    ->classes()
    ->toBeFinal()->ignoring('App\Http\Controllers\Controller');

arch('resources')
    ->expect('App\Http\Resources')
    ->classes()
    ->toBeFinal()->ignoring('App\Http\Resources\Concerns')
    ->toHaveSuffix('Resource')->ignoring('App\Http\Resources\Concerns');

// --- Jobs and commands ---
arch('jobs are thin and queued')
    ->expect('App\Jobs')
    ->classes()
    // TracksSyncProgress is the import jobs' shared trait.
    ->toBeFinal()->ignoring('App\Jobs\Bitrix\TracksSyncProgress')
    ->toHaveSuffix('Job')->ignoring('App\Jobs\Bitrix\TracksSyncProgress')
    ->toImplement('Illuminate\Contracts\Queue\ShouldQueue')->ignoring('App\Jobs\Bitrix\TracksSyncProgress')
    ->toOnlyBeUsedIn(['App\Services', 'App\Jobs', 'Tests'])
    ->not->toUse(['App\Repositories', 'App\Integrations', 'Illuminate\Support\Facades\DB']);

arch('commands are thin')
    ->expect('App\Console\Commands')
    ->toBeFinal()
    ->toHaveSuffix('Command')
    ->not->toUse(['App\Repositories', 'App\Integrations', 'App\Jobs', 'Illuminate\Support\Facades\DB']);

// Observers are entry points too (ARC-12): one service call each.
arch('observers are thin')
    ->expect('App\Observers')
    ->toBeFinal()
    ->toHaveSuffix('Observer')
    ->not->toUse(['App\Repositories', 'App\Integrations', 'App\Jobs', 'Illuminate\Support\Facades\DB']);

// --- Bitrix24 (external API data source, ARC-15) ---
arch('only the gateways talk to external HTTP')
    ->expect(['Illuminate\Support\Facades\Http', 'Illuminate\Http\Client\Factory'])
    ->toOnlyBeUsedIn([
        'App\Integrations\Bitrix24\BitrixGateway',
        'App\Integrations\Bitrix24\BitrixOAuthGateway',
        'Tests',
    ]);

// ARC-4: dependencies come through constructors. failed() on a job is the one
// place nothing can be injected (the worker passes the exception only).
arch('the container is reached only by providers')
    ->expect(['app', 'resolve'])
    ->toOnlyBeUsedIn(['App\Providers', 'App\Jobs\Bitrix\TracksSyncProgress', 'Tests', 'Database']);

arch('only the Bitrix repositories call the gateway')
    ->expect('App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface')
    ->toOnlyBeUsedIn(['App\Integrations\Bitrix24', 'App\Providers', 'App\DTOs\Task\FileDownloadDTO', 'Tests']);

// ARC-14: single-purpose steps inside the service layer, used by services only.
arch('actions')
    ->expect('App\Actions')
    ->toBeFinal()
    ->toBeReadonly()
    ->toHaveSuffix('Action')
    ->toHaveMethod('handle')
    ->not->toHavePublicMethodsBesides(['__construct', 'handle'])
    ->toOnlyBeUsedIn(['App\Services', 'Tests'])
    ->not->toUse([
        'Illuminate\Http\Request',
        'Illuminate\Support\Facades\DB',
        'Illuminate\Database\ConnectionInterface',
        'App\Jobs',
    ]);

// ARC-4: services reach the Bitrix layer through its interfaces (Contracts) and the
// BitrixPayload value reader, never through its classes.
arch('services depend on Bitrix interfaces, not classes')
    ->expect('App\Services')
    ->not->toUse([
        'App\Integrations\Bitrix24\BitrixGateway',
        'App\Integrations\Bitrix24\BitrixOAuthGateway',
        'App\Integrations\Bitrix24\TokenManager',
        'App\Integrations\Bitrix24\PortalServiceAccount',
        'App\Integrations\Bitrix24\BitrixTaskResolver',
        'App\Integrations\Bitrix24\BitrixEchoGuard',
        'App\Integrations\Bitrix24\BitrixDeletionGuard',
        'App\Integrations\Bitrix24\BitrixLoginCodes',
        'App\Integrations\Bitrix24\BitrixRecordFetcher',
        'App\Integrations\Bitrix24\Mappers',
        'App\Integrations\Bitrix24\Repositories',
    ]);

// ARC-6: transactions belong to the services, never to the Bitrix layer.
arch('integrations do not own transactions')
    ->expect('App\Integrations')
    ->not->toUse(['Illuminate\Support\Facades\DB', 'Illuminate\Database\ConnectionInterface']);

// ARC-5: models describe data (relations, casts, scopes); queries and writes live in repositories.
arch('models do not use the DB facade')
    ->expect('App\Models')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('integrations are final')
    ->expect('App\Integrations')
    ->classes()
    ->toBeFinal();

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
