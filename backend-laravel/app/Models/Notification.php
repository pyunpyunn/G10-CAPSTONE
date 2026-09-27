<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $primaryKey = 'notif_id';

    protected $fillable = [
        'message',
        'sent_by',
        'evacuation_event_id',
        'evacuation_center_id',
        'urgency_level_id',
        'scheduled_at',
        'is_recurring',
        'recurrence_type_id',
        'recurrence_end_at',
        'last_sent_at',
        'channel',
        'status',
        'target_filter',
    ];

    protected function casts(): array
    {
        return [
            'is_recurring' => 'boolean',
            'scheduled_at' => 'datetime',
            'recurrence_end_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by', 'user_id');
    }
}
