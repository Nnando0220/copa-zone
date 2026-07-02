<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TournamentEditionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'season' => $this->season,
            'provider' => $this->provider,
            'provider_league_id' => $this->provider_league_id,
            'status' => $this->status,
            'last_synced_at' => $this->last_synced_at,
            'teams_count' => $this->teams_count ?? null,
            'groups_count' => $this->groups_count ?? null,
            'matches_count' => $this->matches_count ?? null,
        ];
    }
}

