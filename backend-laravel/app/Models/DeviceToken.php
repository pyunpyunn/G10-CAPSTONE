<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceToken extends Model
{
    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $fillable = ['device_uuid', 'household_id', 'member_id', 'device_name', 'platform', 'app_role', 'push_provider', 'player_id', 'expo_push_token', 'battery_level', 'signal_strength', 'location_permission_status', 'notification_permission_status', 'last_latitude', 'last_longitude', 'last_location_label', 'last_location_accuracy_m', 'last_location_at', 'last_seen_at', 'is_active', 'logged_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'battery_level' => 'integer', 'signal_strength' => 'integer', 'last_latitude' => 'decimal:7', 'last_longitude' => 'decimal:7', 'last_location_accuracy_m' => 'decimal:2', 'last_location_at' => 'datetime', 'last_seen_at' => 'datetime', 'logged_at' => 'datetime'];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id', 'household_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(HouseholdMember::class, 'member_id', 'member_id');
    }

    public function trackingLogs(): HasMany
    {
        return $this->hasMany(DeviceTrackingLog::class, 'device_token_id', 'id');
    }
}
