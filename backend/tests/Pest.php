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
    ->in('Unit/Services', 'Unit/Actions', 'Unit/Jobs');
