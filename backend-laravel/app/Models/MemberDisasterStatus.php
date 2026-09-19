<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberDisasterStatus extends Model
{
    protected $table = 'member_disaster_statuses';
    protected $primaryKey = 'member_status_id';
    protected $fillable = ['disaster_id', 'household_id', 'member_id', 'status_id', 'previous_status_id', 'report_source', 'reported_by_user_id', 'reported_by_member_id', 'responder_id', 'device_token_id', 'latitude', 'longitude', 'location_label', 'location_accuracy_m', 'battery_level', 'signal_strength', 'evacuation_center_id', 'needs_rescue', 'severity_rank', 'priority_level', 'notes', 'is_verified', 'verified_by_user_id', 'verified_at', 'reported_at'];
    protected function casts(): array { return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'location_accuracy_m' => 'decimal:2', 'battery_level' => 'integer', 'signal_strength' => 'integer', 'needs_rescue' => 'boolean', 'severity_rank' => 'integer', 'is_verified' => 'boolean', 'verified_at' => 'datetime', 'reported_at' => 'datetime', 'created_at' => 'datetime', 'updated_at' => 'datetime']; }
    public function member(): BelongsTo { return $this->belongsTo(HouseholdMember::class, 'member_id', 'member_id'); }
    public function status(): BelongsTo { return $this->belongsTo(MemberStatus::class, 'status_id', 'status_id'); }
    public function disaster(): BelongsTo { return $this->belongsTo(DisasterEvent::class, 'disaster_id', 'event_id'); }
}
