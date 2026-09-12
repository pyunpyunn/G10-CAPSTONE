<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestValidation extends Model
{
    protected $table = 'request_validations';

    protected $primaryKey = 'validation_id';

    protected $fillable = [
        'request_id',
        'validation_status',
        'validator_user_id',
        'validation_notes',
        'missing_information',
        'duplicate_request_id',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'validated_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ResourceRequest::class, 'request_id', 'request_id');
    }
}