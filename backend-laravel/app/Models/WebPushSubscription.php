<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebPushSubscription extends Model
{
    protected $table = 'web_push_subscriptions';

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'user_id',
        'token_id',
        'endpoint_hash',
        'subscription',
        'expires_at',
        'created_at',
        'updated_at',
        'push_after',
    ];

    protected $hidden = [
        'endpoint_hash',
        'subscription',
    ];

    protected function casts(): array
    {
        return [
            'subscription' => 'array',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'push_after' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
