<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DispatchTrip extends Model
{
    protected $table = 'dispatch_trips';

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'dispatch_id',
        'team_id',
        'device_id',
        'profile',
        'started_at',
        'ended_at',
        'distance_m',
        'planned_distance_m',
        'moving_s',
        'stopped_s',
        'avg_speed_ms',
        'status',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(ResponderAssignment::class, 'dispatch_id', 'assignment_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(RescueTeam::class, 'team_id', 'team_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(NavigationDevice::class, 'device_id', 'id');
    }

    public function points(): HasMany
    {
        return $this->hasMany(TripPoint::class, 'trip_id', 'id');
    }
}
