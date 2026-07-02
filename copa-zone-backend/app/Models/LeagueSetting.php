<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueSetting extends Model
{
    use HasUuids;

    protected $fillable = [
        'league_id',
        'points_correct_outcome',
        'points_wrong_outcome',
        'points_exact_score',
        'points_correct_goal_difference',
        'points_correct_outcome_scoreline',
        'prediction_lock_minutes_before_start',
        'allow_prediction_cancellation',
        'late_join_enabled',
        'ranking_visibility',
    ];

    protected function casts(): array
    {
        return [
            'points_correct_outcome' => 'integer',
            'points_wrong_outcome' => 'integer',
            'points_exact_score' => 'integer',
            'points_correct_goal_difference' => 'integer',
            'points_correct_outcome_scoreline' => 'integer',
            'prediction_lock_minutes_before_start' => 'integer',
            'allow_prediction_cancellation' => 'boolean',
            'late_join_enabled' => 'boolean',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }
}
