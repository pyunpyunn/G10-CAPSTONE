<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdTrustedPin extends Model
{
    protected $table = 'household_trusted_pins';

    protected $primaryKey = 'household_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['household_id', 'pin_hash', 'updated_by_user_id'];

    protected $hidden = ['pin_hash'];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id', 'household_id');
    }
}
