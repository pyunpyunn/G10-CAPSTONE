<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    protected $table = 'provinces'; protected $primaryKey = 'province_id'; public $incrementing = false; public $timestamps = false;
    protected $fillable = ['province_code', 'province_name', 'region_id'];
    public function region(): BelongsTo { return $this->belongsTo(Region::class, 'region_id', 'region_id'); }
    public function cities(): HasMany { return $this->hasMany(City::class, 'province_id', 'province_id'); }
}
