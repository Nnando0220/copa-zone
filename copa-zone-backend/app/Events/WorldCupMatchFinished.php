<?php

namespace App\Events;

use App\Models\WorldCupMatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorldCupMatchFinished implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly WorldCupMatch $match)
    {
    }

    public function broadcastAs(): string
    {
        return 'world_cup.match.finished';
    }

    public function broadcastOn(): array
    {
        return [new Channel('world-cup')];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->match->id,
            'provider_fixture_id' => $this->match->provider_fixture_id,
            'status' => $this->match->status,
            'home_score' => $this->match->home_score,
            'away_score' => $this->match->away_score,
            'updated_at' => $this->match->updated_at?->toISOString(),
        ];
    }
}
