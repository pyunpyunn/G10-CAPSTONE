<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResponderFieldReportMember extends Model
{
    protected $table = 'responder_field_report_members';

    protected $primaryKey = 'detail_id';

    protected $fillable = [
        'detail_id',
        'report_id',
        'household_id',
        'disaster_id',
        'member_id',
        'status_id',
        'status_key',
        'assessed_at',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'assessed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ResponderFieldReport::class, 'report_id', 'report_id');
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

    public function status(): BelongsTo
    {
        return $this->belongsTo(MemberStatus::class, 'status_id', 'status_id');
    }
}
