<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\LeagueController;
use App\Http\Controllers\Api\V1\PredictionController;
use App\Http\Controllers\Api\V1\WorldCupController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->middleware('throttle:auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('me');
    Route::patch('/me', [AuthController::class, 'updateProfile'])->name('me.update');

    Route::get('/dashboard', [LeagueController::class, 'dashboard'])->name('dashboard');

    Route::get('/world-cup', [WorldCupController::class, 'show'])->name('world-cup.show');
    Route::get('/world-cup/teams', [WorldCupController::class, 'teams'])->name('world-cup.teams');
    Route::get('/world-cup/groups', [WorldCupController::class, 'groups'])->name('world-cup.groups');
    Route::get('/world-cup/bracket', [WorldCupController::class, 'bracket'])->name('world-cup.bracket');
    Route::get('/world-cup/matches', [WorldCupController::class, 'matches'])->name('world-cup.matches');
    Route::get('/world-cup/matches/{match}', [WorldCupController::class, 'match'])->name('world-cup.matches.show');
    Route::get('/world-cup/sync-status', [WorldCupController::class, 'syncStatus'])->name('world-cup.sync-status');

    Route::get('/leagues', [LeagueController::class, 'index'])->name('leagues.index');
    Route::post('/leagues', [LeagueController::class, 'store'])->name('leagues.store');
    Route::get('/leagues/public', [LeagueController::class, 'publicLeagues'])->name('leagues.public');
    Route::post('/leagues/invites/preview', [LeagueController::class, 'previewByCode'])->name('leagues.invites.preview');
    Route::post('/leagues/join-by-code', [LeagueController::class, 'joinByCode'])->name('leagues.join-by-code');
    Route::get('/leagues/{league}/world-cup', [WorldCupController::class, 'leagueWorldCup'])->name('leagues.world-cup');
    Route::get('/leagues/{league}/world-cup/groups', [WorldCupController::class, 'leagueGroups'])->name('leagues.world-cup.groups');
    Route::get('/leagues/{league}/world-cup/stages', [WorldCupController::class, 'leagueStages'])->name('leagues.world-cup.stages');
    Route::get('/leagues/{league}/world-cup/matches', [WorldCupController::class, 'leagueMatches'])->name('leagues.world-cup.matches');
    Route::get('/leagues/{league}/world-cup/bracket', [WorldCupController::class, 'leagueBracket'])->name('leagues.world-cup.bracket');
    Route::get('/leagues/{league}/predictions', [PredictionController::class, 'index'])->name('leagues.predictions.index');
    Route::post('/leagues/{league}/matches/{match}/prediction', [PredictionController::class, 'store'])->name('leagues.predictions.store');
    Route::put('/leagues/{league}/matches/{match}/prediction', [PredictionController::class, 'store'])->name('leagues.predictions.update');
    Route::delete('/leagues/{league}/predictions/{prediction}', [PredictionController::class, 'destroy'])->name('leagues.predictions.destroy');
    Route::get('/leagues/{league}/ranking', [PredictionController::class, 'ranking'])->name('leagues.ranking');
    Route::get('/leagues/{league}/matches', [WorldCupController::class, 'leagueMatches'])->name('leagues.matches');
    Route::get('/leagues/{league}', [LeagueController::class, 'show'])->name('leagues.show');
    Route::post('/leagues/{league}/join', [LeagueController::class, 'join'])->name('leagues.join');
    Route::delete('/leagues/{league}/membership', [LeagueController::class, 'leave'])->name('leagues.leave');
});
