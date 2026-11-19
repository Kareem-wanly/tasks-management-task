<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

/*
 * PR7c: the app's API lives under /api/v1; Bitrix24's own install and event
 * URLs (registered in the app's settings there) stay where they were.
 */
it('serves the app API under /api/v1 only', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/me')->assertOk();
    $this->getJson('/api/me')->assertNotFound();
    $this->getJson('/api/tasks')->assertNotFound();
});

it('keeps the Bitrix24 install and event URLs outside the version', function (): void {
    expect(Route::has('api.bitrix.install'))->toBeTrue()
        ->and(route('api.bitrix.install', absolute: false))->toBe('/api/bitrix/install')
        ->and(route('api.bitrix.events', absolute: false))->toBe('/api/bitrix/events');

    // Reached (and refused for its missing token), not a missing route.
    $this->postJson('/api/bitrix/events', ['event' => 'ONTASKADD'])->assertUnauthorized();
});

it('names every versioned route api.v1.*', function (): void {
    $versioned = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route): bool => str_starts_with($route->uri(), 'api/v1/'));

    expect($versioned)->not->toBeEmpty()
        ->and($versioned->reject(fn (RouteDefinition $route): bool => str_starts_with((string) $route->getName(), 'api.v1.'))->map->uri()->values()->all())
        ->toBe([]);
});
