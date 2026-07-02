<?php

namespace App\Application\Actions\League;

use App\Models\League;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PreviewLeagueByCodeAction
{
    /**
     * @return array{league: League, already_member: bool}
     */
    public function execute(User $user, string $inviteCode): array
    {
        $league = League::query()
            ->where('invite_code', $inviteCode)
            ->first();

        if (! $league || $league->visibility !== 'private' || $league->join_policy !== 'invite_code') {
            throw ValidationException::withMessages([
                'invite_code' => 'Não encontramos uma liga disponível com esse código. Confira os caracteres e tente novamente.',
            ]);
        }

        if ($league->status !== 'open') {
            throw ValidationException::withMessages([
                'invite_code' => 'Este convite não está mais disponível. Solicite um novo código ao gestor.',
            ]);
        }

        $alreadyMember = $league->members()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if (! $alreadyMember && $league->activeMembers()->count() >= $league->max_members) {
            throw ValidationException::withMessages([
                'invite_code' => 'Essa liga já atingiu o limite de participantes.',
            ]);
        }

        return [
            'league' => $league,
            'already_member' => $alreadyMember,
        ];
    }
}
