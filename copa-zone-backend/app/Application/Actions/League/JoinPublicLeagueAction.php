<?php

namespace App\Application\Actions\League;

use App\Models\League;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JoinPublicLeagueAction
{
    public function execute(User $user, League $league): League
    {
        return DB::transaction(function () use ($user, $league): League {
            $lockedLeague = League::query()
                ->whereKey($league->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedLeague->visibility !== 'public' || $lockedLeague->join_policy !== 'open') {
                throw ValidationException::withMessages([
                    'league' => 'Esta liga não aceita entrada pública.',
                ]);
            }

            $this->join($user, $lockedLeague);

            return $lockedLeague;
        });
    }

    private function join(User $user, League $league): void
    {
        if ($league->status !== 'open') {
            throw ValidationException::withMessages([
                'league' => 'Esta liga não está aberta para novos participantes.',
            ]);
        }

        if ($league->members()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'league' => 'Você já participa desta liga.',
            ]);
        }

        if ($league->activeMembers()->count() >= $league->max_members) {
            throw ValidationException::withMessages([
                'league' => 'Esta liga já está cheia.',
            ]);
        }

        $member = $league->members()->create([
            'user_id' => $user->id,
            'role' => 'participant',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $league->rankings()->firstOrCreate(
            ['league_member_id' => $member->id],
            ['position' => $league->activeMembers()->count()],
        );
    }
}
