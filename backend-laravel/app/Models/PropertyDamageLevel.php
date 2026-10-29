<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyDamageLevel extends Model
{
    protected $table = 'property_damage_levels';

    protected $primaryKey = 'damage_key';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'damage_key',
        'label',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
