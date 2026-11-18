<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Bitrix24 step 3.6: safety net for lost events. Every 5 minutes, what
// changed; every night, a full pass for what has no events (users, project
// members) and for deletions.
Schedule::command('bitrix:sync-changes')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('bitrix:import --sync')->dailyAt('03:00')->withoutOverlapping();

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
