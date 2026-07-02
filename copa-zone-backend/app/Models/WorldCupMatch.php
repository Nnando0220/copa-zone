<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorldCupMatch extends Model
{
    use HasUuids;

    private const UNRESOLVED_TEAM_MARKERS = ['/', '\\', ' or ', ' ou ', 'winner ', 'vencedor ', 'loser ', 'perdedor '];

    protected $table = 'matches';

    protected $fillable = [
        'tournament_edition_id',
        'tournament_group_id',
        'home_team_id',
        'away_team_id',
        'winner_team_id',
        'provider',
        'provider_fixture_id',
        'starts_at',
        'timezone',
        'venue_name',
        'round',
        'status',
        'status_short',
        'elapsed',
        'home_score',
        'away_score',
        'home_penalty_score',
        'away_penalty_score',
        'winner_source',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'elapsed' => 'integer',
            'home_score' => 'integer',
            'away_score' => 'integer',
            'home_penalty_score' => 'integer',
            'away_penalty_score' => 'integer',
        ];
    }

    public function tournamentEdition(): BelongsTo
    {
        return $this->belongsTo(TournamentEdition::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TournamentGroup::class, 'tournament_group_id');
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function winnerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_team_id');
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class, 'match_id');
    }

    public function hasResolvedTeams(): bool
    {
        return $this->teamIsResolved($this->homeTeam) && $this->teamIsResolved($this->awayTeam);
    }

    private function teamIsResolved(?Team $team): bool
    {
        if (! $team) {
            return false;
        }

        foreach ([
            $team->name,
            $team->external_name,
            $team->official_name,
            $team->display_name_pt_br,
            $team->country_code,
            $team->code,
        ] as $value) {
            $normalized = strtolower(trim((string) $value));

            if ($normalized === '') {
                continue;
            }

            foreach (self::UNRESOLVED_TEAM_MARKERS as $marker) {
                if (str_contains($normalized, $marker)) {
                    return false;
                }
            }
        }

        return true;
    }
}
