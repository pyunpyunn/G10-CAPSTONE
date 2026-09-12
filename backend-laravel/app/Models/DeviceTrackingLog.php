<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceTrackingLog extends Model
{
    protected $primaryKey = 'tracking_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['device_token_id', 'household_id', 'member_id', 'latitude', 'longitude', 'location_label', 'accuracy_m', 'location_source', 'is_allowed_location', 'battery_level', 'signal_strength', 'logged_at'];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'accuracy_m' => 'decimal:2', 'is_allowed_location' => 'boolean', 'battery_level' => 'integer', 'signal_strength' => 'integer', 'logged_at' => 'datetime'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(DeviceToken::class, 'device_token_id', 'id');
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id', 'household_id');
    }
}
