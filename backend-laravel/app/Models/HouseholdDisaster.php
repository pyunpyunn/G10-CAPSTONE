<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdDisaster extends Model
{
    protected $primaryKey = 'household_disaster_id';

    public $incrementing = false;

    protected $fillable = ['household_id', 'disaster_id', 'initial_status_id', 'current_status_id', 'last_status_source', 'last_status_notes', 'last_reported_by_user_id', 'last_responder_id', 'last_device_token_id', 'last_latitude', 'last_longitude', 'last_battery_level', 'last_reported_at', 'needs_dispatch', 'priority_level'];

    protected function casts(): array
    {
        return ['needs_dispatch' => 'boolean', 'last_latitude' => 'decimal:7', 'last_longitude' => 'decimal:7', 'last_battery_level' => 'integer', 'last_reported_at' => 'datetime'];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id', 'household_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(HouseholdStatus::class, 'current_status_id', 'status_id');
    }

    public function disaster(): BelongsTo
    {
        return $this->belongsTo(DisasterEvent::class, 'disaster_id', 'event_id');
    }
}
