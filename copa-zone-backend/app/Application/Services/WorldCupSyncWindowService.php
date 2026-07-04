<?php

namespace App\Application\Services;

use App\Models\WorldCupMatch;

class WorldCupSyncWindowService
{
    public function hasMatchesNeedingSync(int $pastMinutes, int $futureMinutes = 0): bool
    {
        return WorldCupMatch::query()
            ->whereNotNull('starts_at')
            ->where('starts_at', '>=', now()->subMinutes($pastMinutes))
            ->where('starts_at', '<=', now()->addMinutes($futureMinutes))
            ->whereNotIn('status', ['finished', 'cancelled', 'postponed'])
            ->exists();
    }
}
