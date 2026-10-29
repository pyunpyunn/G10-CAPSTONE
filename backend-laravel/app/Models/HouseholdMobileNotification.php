<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdMobileNotification extends Model
{
    protected $table = 'household_mobile_notifications';

    protected $primaryKey = 'notification_id';

    protected $fillable = [
        'notification_id',
        'household_id',
        'type',
        'title',
        'message',
        'connection_id',
        'read_at',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id', 'household_id');
    }
}
