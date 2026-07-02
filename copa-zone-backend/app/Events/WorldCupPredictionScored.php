<?php

namespace App\Events;

use App\Http\Resources\PredictionResource;
use App\Models\Prediction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorldCupPredictionScored implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Prediction $prediction)
    {
    }

    public function broadcastAs(): string
    {
        return 'world_cup.prediction.scored';
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('world-cup'),
            new Channel("league.{$this->prediction->league_id}"),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'prediction' => (new PredictionResource($this->prediction->loadMissing(['match.homeTeam', 'match.awayTeam', 'match.group'])))->resolve(),
        ];
    }
}
