<?php

namespace App\Events;

use App\Http\Resources\LeagueRankingResource;
use App\Models\League;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeagueRankingUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly League $league)
    {
    }

    public function broadcastAs(): string
    {
        return 'world_cup.ranking.updated';
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("league.{$this->league->id}")];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $rankings = $this->league->rankings()
            ->with('leagueMember.user')
            ->orderBy('position')
            ->get();

        return [
            'league_id' => $this->league->id,
            'rankings' => LeagueRankingResource::collection($rankings)->resolve(),
        ];
    }
}
