<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResponderLocationLog extends Model
{
    protected $table = 'responder_location_logs';

    protected $primaryKey = 'log_id';

    public $timestamps = false;

    protected $fillable = [
        'responder_id',
        'latitude',
        'longitude',
        'battery_level',
        'signal_strength',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'battery_level' => 'integer',
            'signal_strength' => 'integer',
            'logged_at' => 'datetime',
        ];
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(Responder::class, 'responder_id', 'responder_id');
    }
}
