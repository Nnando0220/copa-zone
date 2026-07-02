<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentGroup extends Model
{
    use HasUuids;

    protected $fillable = [
        'tournament_edition_id',
        'name',
        'external_name',
        'internal_code',
        'display_name',
        'locale',
        'translation_status',
    ];

    public function tournamentEdition(): BelongsTo
    {
        return $this->belongsTo(TournamentEdition::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(WorldCupMatch::class);
    }
}
