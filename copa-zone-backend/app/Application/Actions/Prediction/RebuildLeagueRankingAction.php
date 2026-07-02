<?php

namespace App\Application\Actions\Prediction;

use App\Events\LeagueRankingUpdated;
use App\Models\League;
use App\Models\Prediction;
use Throwable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RebuildLeagueRankingAction
{
    public function execute(League $league, bool $broadcast = true): void
    {
        DB::transaction(function () use ($league): void {
            $members = $league->activeMembers()
                ->with('user')
                ->orderBy('joined_at')
                ->get();

            $rows = $members->map(function ($member) use ($league): array {
                $predictions = Prediction::query()
                    ->where('league_id', $league->id)
                    ->where('league_member_id', $member->id)
                    ->where('status', 'settled')
                    ->get();

                return [
                    'member' => $member,
                    'total_points' => $predictions->sum('points_awarded'),
                    'exact_scores' => $predictions->where('score_reason', 'exact_score')->count(),
                    'correct_goal_differences' => $predictions->where('score_reason', 'goal_difference')->count(),
                    'correct_outcomes' => $predictions->where('score_reason', 'outcome')->count(),
                    'wrong_predictions' => $predictions->where('score_reason', 'wrong')->count(),
                    'settled_predictions' => $predictions->count(),
                    'last_points_at' => $predictions->where('points_awarded', '>', 0)->max('scored_at'),
                ];
            })->sort(function (array $a, array $b): int {
                return $b['total_points'] <=> $a['total_points']
                    ?: $b['exact_scores'] <=> $a['exact_scores']
                    ?: $b['correct_goal_differences'] <=> $a['correct_goal_differences']
                    ?: $b['correct_outcomes'] <=> $a['correct_outcomes']
                    ?: $b['settled_predictions'] <=> $a['settled_predictions']
                    ?: $a['member']->joined_at <=> $b['member']->joined_at;
            })->values();

            $this->persistRows($league, $rows);
        });

        if (! $broadcast) {
            return;
        }

        try {
            LeagueRankingUpdated::dispatch($league->refresh());
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param Collection<int, array<string, mixed>> $rows
     */
    private function persistRows(League $league, Collection $rows): void
    {
        $lastComparable = null;
        $lastPosition = 0;

        foreach ($rows as $index => $row) {
            $comparable = [
                $row['total_points'],
                $row['exact_scores'],
                $row['correct_goal_differences'],
                $row['correct_outcomes'],
                $row['settled_predictions'],
            ];
            $position = $comparable === $lastComparable ? $lastPosition : $index + 1;

            $league->rankings()->updateOrCreate(
                ['league_member_id' => $row['member']->id],
                [
                    'position' => $position,
                    'total_points' => $row['total_points'],
                    'exact_scores' => $row['exact_scores'],
                    'correct_goal_differences' => $row['correct_goal_differences'],
                    'correct_outcomes' => $row['correct_outcomes'],
                    'wrong_predictions' => $row['wrong_predictions'],
                    'settled_predictions' => $row['settled_predictions'],
                    'last_points_at' => $row['last_points_at'],
                ],
            );

            $lastComparable = $comparable;
            $lastPosition = $position;
        }
    }
}
