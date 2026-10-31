<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResponderFieldReport extends Model
{
    protected $table = 'responder_field_reports';

    protected $primaryKey = 'report_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'report_id',
        'responder_id',
        'disaster_id',
        'household_id',
        'latitude',
        'longitude',
        'notes',
        'created_at',
        'assignment_id',
        'submitted_by_user_id',
        'status_log_id',
        'incident_type',
        'incident_type_other',
        'property_damage',
        'death_count',
        'injured_count',
        'idempotency_key',
        'payload_hash',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(Responder::class, 'responder_id', 'responder_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ResponderAssignment::class, 'assignment_id', 'assignment_id');
    }

    public function statusLog(): BelongsTo
    {
        return $this->belongsTo(HouseholdStatusLog::class, 'status_log_id', 'status_log_id');
    }

    public function disaster(): BelongsTo
    {
        return $this->belongsTo(DisasterEvent::class, 'disaster_id', 'event_id');
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id', 'household_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ResponderFieldReportMember::class, 'report_id', 'report_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(ResponderFieldReportDetail::class, 'report_id', 'report_id');
    }

    public function damageLevel(): BelongsTo
    {
        return $this->belongsTo(PropertyDamageLevel::class, 'property_damage', 'damage_key');
    }
}
