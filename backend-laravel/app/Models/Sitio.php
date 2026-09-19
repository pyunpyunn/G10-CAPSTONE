<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sitio extends Model
{
    protected $table = 'sitios';
    protected $primaryKey = 'sitio_id';
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = ['sitio_name', 'barangay_id'];

    public function barangay(): BelongsTo { return $this->belongsTo(Barangay::class, 'barangay_id', 'barangay_id'); }
    public function puroks(): HasMany { return $this->hasMany(Purok::class, 'sitio_id', 'sitio_id'); }
}
