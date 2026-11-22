<?php

declare(strict_types=1);

/*
 * Pest runs the existing PHPUnit test classes unchanged; these settings apply
 * to the Pest-style (closure) tests added from now on.
 */
pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->in('Unit/Services', 'Unit/Actions', 'Unit/Jobs', 'Unit/Integrations');

/**
 * An unsaved user whose roles are known without the database (the model's
 * role cache is filled), for service unit tests with mocked repositories.
 */
function userWithRoles(string ...$roles): App\Models\User
{
    $user = (new App\Models\User)->forceFill(['id' => random_int(1_000, 999_999), 'name' => 'Someone']);
    (fn (): Illuminate\Support\Collection => $this->roleCache = collect($roles))->call($user);

    return $user;
}
