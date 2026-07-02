<?php

namespace App\Application\Actions\League;

use App\Models\League;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JoinLeagueByCodeAction
{
    public function execute(User $user, string $inviteCode): League
    {
        return DB::transaction(function () use ($user, $inviteCode): League {
            $league = League::query()
                ->where('invite_code', $inviteCode)
                ->lockForUpdate()
                ->first();

            if (! $league) {
                throw ValidationException::withMessages([
                    'invite_code' => 'Codigo de convite invalido.',
                ]);
            }

            if ($league->visibility !== 'private' || $league->join_policy !== 'invite_code') {
                throw ValidationException::withMessages([
                    'invite_code' => 'Este codigo nao pertence a uma liga privada.',
                ]);
            }

            $this->join($user, $league);

            return $league;
        });
    }

    private function join(User $user, League $league): void
    {
        if ($league->status !== 'open') {
            throw ValidationException::withMessages([
                'invite_code' => 'Esta liga nao esta aberta para novos participantes.',
            ]);
        }

        if ($league->members()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'invite_code' => 'Voce ja participa desta liga.',
            ]);
        }

        if ($league->activeMembers()->count() >= $league->max_members) {
            throw ValidationException::withMessages([
                'invite_code' => 'Esta liga ja esta cheia.',
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
