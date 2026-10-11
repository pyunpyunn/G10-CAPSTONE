<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusReminder extends Model
{
    protected $primaryKey = 'reminder_id';

    protected $fillable = [
        'event_id', 'household_id', 'member_id', 'attempt', 'status',
        'scheduled_at', 'sent_at', 'stopped_at', 'member_delivery', 'household_delivery',
    ];

    protected function casts(): array
    {
        return ['attempt' => 'integer', 'scheduled_at' => 'datetime', 'sent_at' => 'datetime', 'stopped_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(HouseholdMember::class, 'member_id', 'member_id');
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id', 'household_id');
    }
}
