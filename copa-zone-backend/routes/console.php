<?php

use Illuminate\Foundation\Inspiring;
use App\Models\WorldCupMatch;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('world-cup:sync --force')
    ->dailyAt('06:00')
    ->when(fn (): bool => WorldCupMatch::query()->doesntExist()
        || WorldCupMatch::query()
            ->whereNotNull('starts_at')
            ->where('starts_at', '>=', now()->startOfDay())
            ->where('starts_at', '<=', now()->addHours(20))
            ->whereNotIn('status', ['cancelled', 'postponed'])
            ->exists())
    ->withoutOverlapping();

Schedule::command('world-cup:sync')
    ->dailyAt('12:00')
    ->when(fn (): bool => WorldCupMatch::query()
        ->whereNotNull('starts_at')
        ->where('starts_at', '>=', now()->subMinutes(150))
        ->where('starts_at', '<=', now()->addMinutes(180))
        ->whereNotIn('status', ['cancelled', 'postponed'])
        ->exists())
    ->withoutOverlapping();

Schedule::command('world-cup:sync --essential --matches-only')
    ->everyTenMinutes()
    ->when(fn (): bool => WorldCupMatch::query()
        ->whereNotNull('starts_at')
        ->where('starts_at', '>=', now()->subMinutes(150))
        ->where('starts_at', '<=', now()->addMinutes(15))
        ->whereNotIn('status', ['cancelled', 'postponed'])
        ->exists())
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
