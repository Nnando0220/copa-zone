<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return hash_equals((string) $user->id, (string) $id);
});

Broadcast::channel('league.{leagueId}', function ($user, string $leagueId): bool {
    return $user->leagueMemberships()
        ->where('league_id', $leagueId)
        ->where('status', 'active')
        ->exists();
});
