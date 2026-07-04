<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Support\OpenLigaDbTranslationService;
use App\Support\WorldCupMatchResultNormalizer;

class WorldCupMatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $translator = app(OpenLigaDbTranslationService::class);
        $matchState = $this->matchState($request);
        $canPredict = $this->can_predict ?? ($matchState === 'open_for_prediction' && $this->resource->hasResolvedTeams());
        $displayTimezone = (string) config('services.openligadb.display_timezone', 'America/Sao_Paulo');
        $result = WorldCupMatchResultNormalizer::normalize($this->resource);

        return [
            'id' => $this->id,
            'provider_fixture_id' => $this->provider_fixture_id,
            'starts_at' => $this->starts_at,
            'starts_at_br' => $this->starts_at?->copy()->timezone($displayTimezone)->toIso8601String(),
            'lock_at' => $this->starts_at,
            'timezone' => $displayTimezone,
            'provider_timezone' => $this->timezone,
            'venue_name' => $this->venue_name,
            'round' => $this->round,
            'status' => $this->status,
            'status_label' => $translator->translateStatus($this->status),
            'match_state' => $matchState,
            'match_state_label' => $translator->translateStatus($matchState),
            'status_short' => $this->status_short,
            'elapsed' => $this->elapsed,
            'home_score' => $result['home_score'],
            'away_score' => $result['away_score'],
            'home_penalty_score' => $result['home_penalty_score'],
            'away_penalty_score' => $result['away_penalty_score'],
            'winner_team_id' => $this->winner_team_id,
            'winner_side' => $this->winnerSide(),
            'winner_source' => $result['winner_source'],
            'prediction_status' => $canPredict ? 'open' : ($this->status === 'finished' ? 'settled' : 'locked'),
            'can_predict' => (bool) $canPredict,
            'group_code' => $this->group_code,
            'bracket_stage' => $this->bracket_stage,
            'bracket_order' => $this->bracket_order,
            'slot_home_label' => $this->slot_home_label,
            'slot_away_label' => $this->slot_away_label,
            'group' => $this->whenLoaded('group', fn () => $this->group ? [
                'id' => $this->group->id,
                'name' => $this->group->name,
                'code' => $this->group_code ?: $this->group->internal_code,
                'display_name' => $this->group_code ? 'Grupo '.$this->group_code : ($this->group->display_name ?: $this->group->name),
            ] : null),
            'home_team' => $this->teamResource('home'),
            'away_team' => $this->teamResource('away'),
            'winner_team' => $this->whenLoaded('winnerTeam', fn () => $this->winnerTeam ? new TeamResource($this->winnerTeam) : null),
        ];
    }

    private function teamResource(string $side): mixed
    {
        $resolved = $this->resource->getAttribute("resolved_{$side}_team");

        if ($resolved) {
            return new TeamResource($resolved);
        }

        $relation = $side === 'home' ? 'homeTeam' : 'awayTeam';

        return $this->whenLoaded($relation, fn () => $this->{$relation} ? new TeamResource($this->{$relation}) : null);
    }

    private function winnerSide(): ?string
    {
        if ($this->winner_team_id === null) {
            return null;
        }

        if ($this->winner_team_id === $this->home_team_id) {
            return 'home';
        }

        if ($this->winner_team_id === $this->away_team_id) {
            return 'away';
        }

        return null;
    }

    private function matchState(Request $request): string
    {
        $override = $this->resource->getAttribute('match_state_override');

        if (is_string($override) && $override !== '') {
            return $override;
        }

        if (! $this->resource->hasResolvedTeams()) {
            return 'awaiting_teams';
        }

        if (in_array($this->status, ['finished', 'postponed', 'cancelled', 'unknown'], true)) {
            return $this->status;
        }

        if ($this->starts_at === null) {
            return 'unknown';
        }

        if ($this->starts_at->isPast()) {
            return $this->status === 'in_progress_unconfirmed' ? 'in_progress_unconfirmed' : 'locked';
        }

        return 'open_for_prediction';
    }
}
