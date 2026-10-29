<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncEvent extends Model
{
    protected $table = 'sync_events';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'client_event_id',
        'device_id',
        'type',
        'status',
        'error_message',
        'received_at',
        'result_json',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'result_json' => 'array',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(NavigationDevice::class, 'device_id', 'id');
    }
}
