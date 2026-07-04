<?php

use App\Application\Services\WorldCupSyncWindowService;
use Illuminate\Foundation\Inspiring;
use App\Models\WorldCupMatch;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$hasWorldCupSyncWindow = static fn (int $pastMinutes, int $futureMinutes = 0): bool => app(WorldCupSyncWindowService::class)
    ->hasMatchesNeedingSync($pastMinutes, $futureMinutes);

Schedule::command('world-cup:sync --force')
    ->dailyAt('06:00')
    ->when(fn (): bool => WorldCupMatch::query()->doesntExist() || $hasWorldCupSyncWindow(0, 20 * 60))
    ->withoutOverlapping();

Schedule::command('world-cup:sync')
    ->hourly()
    ->when(fn (): bool => $hasWorldCupSyncWindow(360, 180))
    ->withoutOverlapping();

Schedule::command('world-cup:sync --essential --matches-only')
    ->everyTenMinutes()
    ->when(fn (): bool => $hasWorldCupSyncWindow(360, 15))
    ->withoutOverlapping();

Schedule::command('world-cup:score-predictions')
    ->everyTenMinutes()
    ->withoutOverlapping();

Schedule::command('world-cup:broadcast-prediction-locks')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('world-cup:reconcile')
    ->dailyAt('23:50')
    ->withoutOverlapping();
