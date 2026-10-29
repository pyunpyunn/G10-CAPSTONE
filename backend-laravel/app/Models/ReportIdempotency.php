<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportIdempotency extends Model
{
    protected $table = 'report_idempotency';

    protected $primaryKey = 'scope';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'scope',
        'request_hash',
        'response_status',
        'response_body',
        'content_type',
        'created_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
