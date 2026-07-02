<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WorldCupSyncState extends Model
{
    use HasUuids;

    protected $fillable = [
        'provider',
        'scope',
        'shortcut',
        'season',
        'status',
        'last_started_at',
        'last_finished_at',
        'last_changed_at',
        'next_attempt_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'last_started_at' => 'datetime',
            'last_finished_at' => 'datetime',
            'last_changed_at' => 'datetime',
            'next_attempt_at' => 'datetime',
        ];
    }
}
