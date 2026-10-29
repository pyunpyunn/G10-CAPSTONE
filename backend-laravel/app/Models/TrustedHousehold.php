<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustedHousehold extends Model
{
    protected $table = 'trusted_households';

    protected $primaryKey = 'connection_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'connection_id',
        'requesting_household_id',
        'trusted_household_id',
        'reason',
        'validation_status',
        'member_relationships',
        'created_by_user_id',
        'validated_by_user_id',
        'validated_at',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'member_relationships' => 'array',
            'validated_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function requestingHousehold(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'requesting_household_id', 'household_id');
    }

    public function trustedHousehold(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'trusted_household_id', 'household_id');
    }
}
