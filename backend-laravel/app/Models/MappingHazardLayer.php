<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MappingHazardLayer extends Model
{
    protected $table = 'mapping_hazard_layers';

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'barangay_id',
        'name',
        'source',
        'source_url',
        'assessed_at',
        'geojson',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'geojson' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
