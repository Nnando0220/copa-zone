<?php

namespace App\Events;

use App\Models\League;
use App\Models\WorldCupMatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorldCupPredictionLockReached implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly League $league,
        public readonly WorldCupMatch $match,
    ) {
    }

    public function broadcastAs(): string
    {
        return 'world_cup.predictions.locked';
    }

    public function broadcastOn(): array
    {
        return [new Channel("league.{$this->league->id}")];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'league_id' => $this->league->id,
            'match_id' => $this->match->id,
            'provider_fixture_id' => $this->match->provider_fixture_id,
            'lock_at' => $this->match->starts_at?->copy()->subMinutes(
                max(0, (int) ($this->league->settings?->prediction_lock_minutes_before_start ?? 0)),
            )?->toISOString(),
            'updated_at' => now()->toISOString(),
        ];
    }
}
