<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'address_id';

    public $incrementing = false;

    protected $fillable = ['street_address', 'street', 'barangay_id', 'sitio_id', 'purok_sitio', 'house_number', 'purok_id', 'zipcode_id', 'zip_code', 'full_address'];

    public function households(): HasMany
    {
        return $this->hasMany(Household::class, 'address_id', 'address_id');
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class, 'barangay_id', 'barangay_id');
    }

    public function purok(): BelongsTo
    {
        return $this->belongsTo(Purok::class, 'purok_id', 'purok_id');
    }
}
