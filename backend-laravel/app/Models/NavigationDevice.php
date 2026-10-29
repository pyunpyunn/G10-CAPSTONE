<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NavigationDevice extends Model
{
    protected $table = 'devices';

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'user_id',
        'team_id',
        'device_uuid',
        'last_sync_at',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'last_sync_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(RescueTeam::class, 'team_id', 'team_id');
    }
}
