<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    protected $table = 'cities'; protected $primaryKey = 'city_id'; public $incrementing = false; public $timestamps = false;
    protected $fillable = ['city_code', 'city_name', 'province_id'];
    public function province(): BelongsTo { return $this->belongsTo(Province::class, 'province_id', 'province_id'); }
    public function barangays(): HasMany { return $this->hasMany(Barangay::class, 'city_id', 'city_id'); }
}
