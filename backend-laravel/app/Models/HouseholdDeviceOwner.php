<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdDeviceOwner extends Model
{
    protected $table = 'household_device_owners';

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'household_id',
        'device_uuid',
        'member_id',
        'selection_token_id',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id', 'household_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(HouseholdMember::class, 'member_id', 'member_id');
    }
}
