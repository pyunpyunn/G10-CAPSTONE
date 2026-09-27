<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisasterBroadcast extends Model
{
    protected $table = 'disaster_broadcasts';

    protected $primaryKey = 'broadcast_id';

    protected $fillable = [
        'broadcast_title',
        'disaster_id',
        'sent_by_admin_id',
        'severity_id',
        'scope_type',
        'target_purok_id',
        'target_area_id',
        'message',
        'allowed_statuses',
        'channel',
        'status',
        'notification_id',
        'weather_log_id',
        'sent_at',
        'target_area_label',
        'direct_impact_puroks_json',
        'allowed_statuses_json',
        'recipient_count',
        'push_status',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'direct_impact_puroks_json' => 'array',
            'allowed_statuses_json' => 'array',
            'recipient_count' => 'integer',
        ];
    }

    public function disaster(): BelongsTo
    {
        return $this->belongsTo(DisasterEvent::class, 'disaster_id', 'event_id');
    }

    public function severity(): BelongsTo
    {
        return $this->belongsTo(SeverityLevel::class, 'severity_id', 'severity_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_admin_id', 'user_id');
    }

    public function weatherLog(): BelongsTo
    {
        return $this->belongsTo(WeatherLog::class, 'weather_log_id', 'weather_log_id');
    }
}
