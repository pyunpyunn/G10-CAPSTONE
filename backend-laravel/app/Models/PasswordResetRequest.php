<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordResetRequest extends Model
{
    protected $table = 'password_reset_requests';

    protected $primaryKey = 'request_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'request_id',
        'user_id',
        'login_value',
        'verification_method',
        'token_hash',
        'failed_attempts',
        'expires_at',
        'verified_at',
        'consumed_at',
        'requested_ip',
        'user_agent',
        'created_at',
        'updated_at',
        'otp_subject_key',
        'household_id',
        'code_hash',
        'sent_at',
    ];

    protected $hidden = [
        'token_hash',
        'code_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
