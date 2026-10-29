<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RealtimeOutbox extends Model
{
    protected $table = 'realtime_outbox';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'topics',
        'available_at',
        'claim_token',
        'claimed_until',
        'attempts',
        'created_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }
}
