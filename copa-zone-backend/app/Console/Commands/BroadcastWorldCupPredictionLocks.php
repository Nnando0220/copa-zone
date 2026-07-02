<?php

namespace App\Console\Commands;

use App\Events\WorldCupPredictionLockReached;
use App\Models\League;
use App\Models\WorldCupMatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class BroadcastWorldCupPredictionLocks extends Command
{
    protected $signature = 'world-cup:broadcast-prediction-locks';

    protected $description = 'Emite eventos WebSocket quando palpites da Copa chegam ao horario de bloqueio.';

    public function handle(): int
    {
        $now = now();
        $windowStart = $now->copy()->subMinute();
        $dispatched = 0;

        League::query()
            ->with('settings')
            ->where('status', 'open')
            ->chunkById(100, function ($leagues) use ($now, $windowStart, &$dispatched): void {
                foreach ($leagues as $league) {
                    $minutes = max(0, (int) ($league->settings?->prediction_lock_minutes_before_start ?? 0));
                    $startsAfter = $windowStart->copy()->addMinutes($minutes);
                    $startsUntil = $now->copy()->addMinutes($minutes);

                    WorldCupMatch::query()
                        ->with(['homeTeam', 'awayTeam'])
                        ->whereNotNull('starts_at')
                        ->where('starts_at', '>', $startsAfter)
                        ->where('starts_at', '<=', $startsUntil)
                        ->whereNotIn('status', ['finished', 'postponed', 'cancelled', 'unknown'])
                        ->orderBy('starts_at')
                        ->get()
                        ->filter(fn (WorldCupMatch $match): bool => $match->hasResolvedTeams())
                        ->each(function (WorldCupMatch $match) use ($league, $minutes, &$dispatched): void {
                            $cacheKey = "world-cup:prediction-lock-broadcast:{$league->id}:{$match->id}:{$minutes}";
                            $ttl = $match->starts_at?->copy()->addHours(6) ?? now()->addDay();

                            if (! Cache::add($cacheKey, true, $ttl)) {
                                return;
                            }

                            WorldCupPredictionLockReached::dispatch($league, $match);
                            $dispatched++;
                        });
                }
            });

        $this->info("Eventos de bloqueio emitidos: {$dispatched}");

        return self::SUCCESS;
    }
}
