<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueRanking extends Model
{
    use HasUuids;

    protected $fillable = [
        'league_id',
        'league_member_id',
        'position',
        'total_points',
        'exact_scores',
        'correct_goal_differences',
        'correct_outcomes',
        'wrong_predictions',
        'settled_predictions',
        'last_points_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'total_points' => 'integer',
            'exact_scores' => 'integer',
            'correct_goal_differences' => 'integer',
            'correct_outcomes' => 'integer',
            'wrong_predictions' => 'integer',
            'settled_predictions' => 'integer',
            'last_points_at' => 'datetime',
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
}
