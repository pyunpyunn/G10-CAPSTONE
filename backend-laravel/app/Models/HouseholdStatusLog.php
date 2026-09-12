<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdStatusLog extends Model
{
    protected $primaryKey = 'status_log_id';

    protected $fillable = ['disaster_id', 'household_id', 'status_id', 'source', 'submitted_by_user_id', 'responder_id', 'device_token_id', 'latitude', 'longitude', 'location_label', 'location_accuracy_m', 'battery_level', 'signal_strength', 'notes', 'submitted_at', 'reviewed_by_user_id', 'reviewed_at'];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'location_accuracy_m' => 'decimal:2', 'battery_level' => 'integer', 'signal_strength' => 'integer', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id', 'household_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(HouseholdStatus::class, 'status_id', 'status_id');
    }

    public function disaster(): BelongsTo
    {
        return $this->belongsTo(DisasterEvent::class, 'disaster_id', 'event_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(DeviceToken::class, 'device_token_id', 'id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id', 'user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id', 'user_id');
    }
}
