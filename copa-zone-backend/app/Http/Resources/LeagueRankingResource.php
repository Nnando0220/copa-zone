<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeagueRankingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'league_id' => $this->league_id,
            'league_member_id' => $this->league_member_id,
            'position' => $this->position,
            'total_points' => $this->total_points,
            'exact_scores' => $this->exact_scores,
            'correct_goal_differences' => $this->correct_goal_differences,
            'correct_outcomes' => $this->correct_outcomes,
            'wrong_predictions' => $this->wrong_predictions,
            'settled_predictions' => $this->settled_predictions,
            'last_points_at' => $this->last_points_at,
            'member' => $this->whenLoaded('leagueMember', fn () => [
                'id' => $this->leagueMember->id,
                'role' => $this->leagueMember->role,
                'joined_at' => $this->leagueMember->joined_at,
                'user' => $this->leagueMember->relationLoaded('user') ? new UserResource($this->leagueMember->user) : null,
            ]),
        ];
    }
}
