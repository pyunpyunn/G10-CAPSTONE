<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Purok extends Model
{
    protected $primaryKey = 'purok_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['purok_name', 'sitio_id'];

    public function sitio(): BelongsTo
    {
        return $this->belongsTo(Sitio::class, 'sitio_id', 'sitio_id');
    }
}
