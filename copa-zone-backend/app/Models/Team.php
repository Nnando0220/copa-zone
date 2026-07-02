<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'external_name',
        'official_name',
        'display_name_pt_br',
        'country_code',
        'code',
        'country',
        'logo_url',
        'provider',
        'provider_team_id',
    ];

    public function homeMatches(): HasMany
    {
        return $this->hasMany(WorldCupMatch::class, 'home_team_id');
    }

    public function awayMatches(): HasMany
    {
        return $this->hasMany(WorldCupMatch::class, 'away_team_id');
    }
}
