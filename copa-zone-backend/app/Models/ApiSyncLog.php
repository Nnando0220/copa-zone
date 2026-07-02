<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ApiSyncLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'provider',
        'operation',
        'scope',
        'priority',
        'status',
        'http_status',
        'calls_count',
        'duration_ms',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
