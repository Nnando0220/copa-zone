<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class TournamentGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $teams = $this->whenLoaded('matches', function (): Collection {
            return $this->matches
                ->flatMap(fn ($match) => [$match->homeTeam, $match->awayTeam])
                ->filter()
                ->unique('id')
                ->values();
        });

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->internal_code,
            'display_name' => $this->display_name ?: $this->name,
            'translation_status' => $this->translation_status,
            'matches_count' => $this->whenCounted('matches'),
            'teams' => $this->whenLoaded('matches', fn () => TeamResource::collection($teams)),
            'matches' => $this->whenLoaded('matches', fn () => WorldCupMatchResource::collection($this->matches)),
        ];
    }
}
