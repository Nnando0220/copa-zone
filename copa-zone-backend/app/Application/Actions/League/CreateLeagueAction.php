<?php

namespace App\Application\Actions\League;

use App\Models\League;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateLeagueAction
{
    /**
     * @param array{name: string, visibility: string, max_members: int} $data
     */
    public function execute(User $owner, array $data): League
    {
        return DB::transaction(function () use ($owner, $data): League {
            $visibility = $data['visibility'];

            $league = League::create([
                'owner_user_id' => $owner->id,
                'name' => $data['name'],
                'visibility' => $visibility,
                'join_policy' => $visibility === 'private' ? 'invite_code' : 'open',
                'invite_code' => $visibility === 'private' ? $this->generateInviteCode() : null,
                'max_members' => $data['max_members'],
                'status' => 'open',
            ]);

            $league->settings()->create([
                'points_correct_outcome' => 3,
                'points_wrong_outcome' => 0,
                'points_exact_score' => 5,
                'points_correct_goal_difference' => 3,
                'points_correct_outcome_scoreline' => 2,
                'prediction_lock_minutes_before_start' => 0,
                'allow_prediction_cancellation' => true,
                'late_join_enabled' => true,
                'ranking_visibility' => 'members',
            ]);

            $member = $league->members()->create([
                'user_id' => $owner->id,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $league->rankings()->create([
                'league_member_id' => $member->id,
                'position' => 1,
            ]);

            return $league;
        });
    }

    private function generateInviteCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (League::where('invite_code', $code)->exists());

        return $code;
    }
}
