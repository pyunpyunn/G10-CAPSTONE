<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    protected $table = 'regions'; protected $primaryKey = 'region_id'; public $incrementing = false; public $timestamps = false;
    protected $fillable = ['region_code', 'region_name'];
    public function provinces(): HasMany { return $this->hasMany(Province::class, 'region_id', 'region_id'); }
}
