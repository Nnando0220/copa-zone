<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentEdition extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'season',
        'provider',
        'provider_league_id',
        'status',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'season' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    public function groups(): HasMany
    {
        return $this->hasMany(TournamentGroup::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(WorldCupMatch::class);
    }
}

