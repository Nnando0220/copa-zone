<?php

namespace App\Events;

use App\Models\TournamentGroup;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorldCupStageUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly TournamentGroup $stage)
    {
    }

    public function broadcastAs(): string
    {
        return 'world_cup.stage.updated';
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
            'stage_id' => $this->stage->id,
            'code' => $this->stage->internal_code,
            'display_name' => $this->stage->display_name,
            'updated_at' => $this->stage->updated_at?->toISOString(),
        ];
    }
}
