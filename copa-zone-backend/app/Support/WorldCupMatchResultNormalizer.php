<?php

namespace App\Support;

class WorldCupMatchResultNormalizer
{
    /**
     * @return array{
     *     home_score: int|null,
     *     away_score: int|null,
     *     home_penalty_score: int|null,
     *     away_penalty_score: int|null,
     *     winner_source: string|null
     * }
     */
    public static function normalize(object $match): array
    {
        $homeScore = $match->home_score;
        $awayScore = $match->away_score;
        $homePenaltyScore = $match->home_penalty_score;
        $awayPenaltyScore = $match->away_penalty_score;
        $winnerSource = $match->winner_source;

        if ($winnerSource !== 'penalties') {
            return [
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'home_penalty_score' => null,
                'away_penalty_score' => null,
                'winner_source' => $winnerSource,
            ];
        }

        if (
            self::scoresAreEqual($homeScore, $awayScore)
            && self::scoresAreDifferent($homePenaltyScore, $awayPenaltyScore)
        ) {
            if (self::isPlausiblePenaltyShootout($homePenaltyScore, $awayPenaltyScore)) {
                return [
                    'home_score' => $homeScore,
                    'away_score' => $awayScore,
                    'home_penalty_score' => $homePenaltyScore,
                    'away_penalty_score' => $awayPenaltyScore,
                    'winner_source' => 'penalties',
                ];
            }

            return [
                'home_score' => $homePenaltyScore,
                'away_score' => $awayPenaltyScore,
                'home_penalty_score' => null,
                'away_penalty_score' => null,
                'winner_source' => 'extra_time',
            ];
        }

        return [
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'home_penalty_score' => null,
            'away_penalty_score' => null,
            'winner_source' => self::scoresAreDifferent($homeScore, $awayScore) ? 'score' : 'tiebreaker',
        ];
    }

    private static function isPlausiblePenaltyShootout(mixed $homeScore, mixed $awayScore): bool
    {
        if (! self::scoresAreDifferent($homeScore, $awayScore)) {
            return false;
        }

        $home = (int) $homeScore;
        $away = (int) $awayScore;

        return max($home, $away) >= 3 || ($home + $away) >= 5;
    }

    private static function scoresAreEqual(mixed $homeScore, mixed $awayScore): bool
    {
        return $homeScore !== null
            && $awayScore !== null
            && (int) $homeScore === (int) $awayScore;
    }

    private static function scoresAreDifferent(mixed $homeScore, mixed $awayScore): bool
    {
        return $homeScore !== null
            && $awayScore !== null
            && (int) $homeScore !== (int) $awayScore;
    }
}
