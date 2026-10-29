<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberStatusLog extends Model
{
    protected $table = 'member_status_logs';

    protected $primaryKey = 'member_status_log_id';

    public $timestamps = false;

    protected $fillable = [
        'member_status_log_id',
        'disaster_id',
        'household_id',
        'member_id',
        'from_status_id',
        'to_status_id',
        'report_source',
        'reported_by_user_id',
        'reported_by_member_id',
        'responder_id',
        'device_token_id',
        'latitude',
        'longitude',
        'location_label',
        'location_accuracy_m',
        'battery_level',
        'signal_strength',
        'notes',
        'reported_at',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'logged_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(HouseholdMember::class, 'member_id', 'member_id');
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id', 'household_id');
    }

    public function disaster(): BelongsTo
    {
        return $this->belongsTo(DisasterEvent::class, 'disaster_id', 'event_id');
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(MemberStatus::class, 'from_status_id', 'status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(MemberStatus::class, 'to_status_id', 'status_id');
    }
}
