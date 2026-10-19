<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CachedRoute extends Model
{
    protected $table = 'cached_routes';

    protected $fillable = [
        'team_id', 'household_id', 'evac_center_id', 'target_key', 'profile', 'polyline6', 'steps',
        'distance_m', 'duration_s', 'status', 'failure_reason', 'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'distance_m' => 'integer',
            'duration_s' => 'integer',
            'computed_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(RescueTeam::class, 'team_id', 'team_id');
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id', 'household_id');
    }

    public function evacuationCenter(): BelongsTo
    {
        return $this->belongsTo(EvacuationCenter::class, 'evac_center_id', 'evacuation_center_id');
    }
}