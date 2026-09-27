<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingInquiry extends Model
{
    protected $table = 'landing_inquiries';

    protected $primaryKey = 'inquiry_id';

    protected $fillable = [
        'name',
        'organization',
        'email',
        'message',
        'status',
        'source_page',
        'ip_address',
        'user_agent',
        'responded_at',
        'handled_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_user_id', 'user_id');
    }
}
