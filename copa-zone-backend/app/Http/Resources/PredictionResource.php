<?php

namespace App\Http\Resources;

use App\Support\OpenLigaDbTranslationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PredictionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $translator = app(OpenLigaDbTranslationService::class);

        return [
            'id' => $this->id,
            'league_id' => $this->league_id,
            'match_id' => $this->match_id,
            'predicted_home_score' => $this->predicted_home_score,
            'predicted_away_score' => $this->predicted_away_score,
            'predicted_winner_side' => $this->predicted_winner_side,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at,
            'locked_at' => $this->locked_at,
            'scored_at' => $this->scored_at,
            'points_awarded' => $this->points_awarded,
            'score_reason' => $this->score_reason,
            'score_reason_label' => $translator->translateScoreReason($this->score_reason),
            'prediction_version' => $this->prediction_version,
            'match' => $this->whenLoaded('match', fn () => new WorldCupMatchResource($this->match)),
        ];
    }
}
