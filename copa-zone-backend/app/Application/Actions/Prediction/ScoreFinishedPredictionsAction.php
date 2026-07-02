<?php

namespace App\Application\Actions\Prediction;

use App\Events\WorldCupPredictionScored;
use App\Models\League;
use App\Models\Prediction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScoreFinishedPredictionsAction
{
    public function __construct(private readonly RebuildLeagueRankingAction $rebuildRanking)
    {
    }

    public function execute(bool $rescore = false): int
    {
        $scored = 0;
        $affectedLeagueIds = collect();

        Prediction::query()
            ->with(['league.settings', 'match'])
            ->whereIn('status', $rescore ? ['pending', 'settled'] : ['pending'])
            ->whereHas('match', fn ($query) => $query
                ->where('status', 'finished')
                ->whereNotNull('home_score')
                ->whereNotNull('away_score'))
            ->chunkById(100, function (Collection $predictions) use (&$scored, $affectedLeagueIds): void {
                foreach ($predictions as $prediction) {
                    DB::transaction(function () use ($prediction, &$scored, $affectedLeagueIds): void {
                        [$points, $reason] = $this->scorePrediction($prediction);

                        $prediction->forceFill([
                            'status' => 'settled',
                            'scored_at' => now(),
                            'points_awarded' => $points,
                            'score_reason' => $reason,
                        ])->save();

                        WorldCupPredictionScored::dispatch($prediction->refresh());

                        $affectedLeagueIds->push($prediction->league_id);
                        $scored++;
                    });
                }
            });

        $affectedLeagueIds->unique()->each(function (string $leagueId): void {
            $league = League::find($leagueId);

            if ($league) {
                $this->rebuildRanking->execute($league);
            }
        });

        return $scored;
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function scorePrediction(Prediction $prediction): array
    {
        $match = $prediction->match;
        $settings = $prediction->league->settings;
        $winnerMatches = $this->winnerSelectionMatches($prediction);

        if (
            $prediction->predicted_home_score === $match->home_score
            && $prediction->predicted_away_score === $match->away_score
            && $winnerMatches
        ) {
            return [(int) ($settings?->points_exact_score ?? 5), 'exact_score'];
        }

        $predictedDifference = $prediction->predicted_home_score - $prediction->predicted_away_score;
        $officialDifference = $match->home_score - $match->away_score;

        if ($predictedDifference === $officialDifference && $winnerMatches) {
            return [(int) ($settings?->points_correct_goal_difference ?? 3), 'goal_difference'];
        }

        if ($this->predictedOutcome($prediction) === $this->officialOutcome($match)) {
            return [(int) ($settings?->points_correct_outcome_scoreline ?? 2), 'outcome'];
        }

        return [0, 'wrong'];
    }

    private function outcome(int $homeScore, int $awayScore): string
    {
        return match (true) {
            $homeScore > $awayScore => 'home_win',
            $homeScore < $awayScore => 'away_win',
            default => 'draw',
        };
    }

    private function predictedOutcome(Prediction $prediction): string
    {
        if (
            $prediction->predicted_home_score === $prediction->predicted_away_score
            && in_array($prediction->predicted_winner_side, ['home', 'away'], true)
        ) {
            return $prediction->predicted_winner_side.'_win';
        }

        return $this->outcome($prediction->predicted_home_score, $prediction->predicted_away_score);
    }

    private function officialOutcome($match): string
    {
        $winnerSide = $this->officialWinnerSide($match);

        if ($match->home_score === $match->away_score && $winnerSide !== null) {
            return $winnerSide.'_win';
        }

        return $this->outcome($match->home_score, $match->away_score);
    }

    private function winnerSelectionMatches(Prediction $prediction): bool
    {
        $match = $prediction->match;
        $winnerSide = $this->officialWinnerSide($match);

        if ($match->home_score !== $match->away_score || $winnerSide === null) {
            return true;
        }

        return $prediction->predicted_winner_side === $winnerSide;
    }

    private function officialWinnerSide($match): ?string
    {
        if ($match->winner_team_id === null) {
            return null;
        }

        if ($match->winner_team_id === $match->home_team_id) {
            return 'home';
        }

        if ($match->winner_team_id === $match->away_team_id) {
            return 'away';
        }

        return null;
    }
}
