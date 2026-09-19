<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccommodationType extends Model
{
    protected $table = 'accommodation_types';

    protected $primaryKey = 'type_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'type_key',
        'type_label',
    ];

    public function units(): HasMany
    {
        return $this->hasMany(AccommodationUnit::class, 'type_id', 'type_id');
    }
}
