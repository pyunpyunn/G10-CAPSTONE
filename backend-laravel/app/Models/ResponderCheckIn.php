<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResponderCheckIn extends Model
{
    protected $table = 'responder_check_ins';

    protected $primaryKey = 'check_in_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'check_in_id',
        'responder_id',
        'disaster_id',
        'team_id',
        'affected_area_id',
        'household_id',
        'member_id',
        'latitude',
        'longitude',
        'check_in_method',
        'status_id',
        'notes',
        'checked_in_at',
        'checked_out_at',
        'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
        ];
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(Responder::class, 'responder_id', 'responder_id');
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id', 'household_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(HouseholdMember::class, 'member_id', 'member_id');
    }
}
