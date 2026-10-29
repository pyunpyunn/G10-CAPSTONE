<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RememberedDevice extends Model
{
    protected $table = 'remembered_devices';

    protected $primaryKey = 'remembered_device_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'remembered_device_id',
        'user_id',
        'token_hash',
        'device_name',
        'platform',
        'ip_address',
        'user_agent',
        'last_used_at',
        'expires_at',
        'revoked_at',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
