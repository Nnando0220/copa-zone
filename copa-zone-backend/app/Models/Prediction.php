<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prediction extends Model
{
    use HasUuids;

    protected $fillable = [
        'league_id',
        'league_member_id',
        'match_id',
        'predicted_home_score',
        'predicted_away_score',
        'predicted_winner_side',
        'status',
        'submitted_at',
        'locked_at',
        'scored_at',
        'points_awarded',
        'score_reason',
        'prediction_version',
    ];

    protected function casts(): array
    {
        return [
            'predicted_home_score' => 'integer',
            'predicted_away_score' => 'integer',
            'submitted_at' => 'datetime',
            'locked_at' => 'datetime',
            'scored_at' => 'datetime',
            'points_awarded' => 'integer',
            'prediction_version' => 'integer',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function leagueMember(): BelongsTo
    {
        return $this->belongsTo(LeagueMember::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(WorldCupMatch::class, 'match_id');
    }
}
