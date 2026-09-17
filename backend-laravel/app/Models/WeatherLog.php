<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeatherLog extends Model
{
    protected $table = 'weather_logs';

    protected $primaryKey = 'weather_log_id';

    protected $fillable = [
        'disaster_id',
        'source_name',
        'source_url',
        'condition_name',
        'temperature',
        'rainfall_mm',
        'wind_speed',
        'wind_direction',
        'humidity',
        'advisory_title',
        'advisory_text',
        'raw_payload',
        'observed_at',
    ];

    protected function casts(): array
    {
        return [
            'temperature' => 'decimal:2',
            'rainfall_mm' => 'decimal:2',
            'wind_speed' => 'decimal:2',
            'humidity' => 'integer',
            'raw_payload' => 'array',
            'observed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function disaster(): BelongsTo
    {
        return $this->belongsTo(DisasterEvent::class, 'disaster_id', 'event_id');
    }
}