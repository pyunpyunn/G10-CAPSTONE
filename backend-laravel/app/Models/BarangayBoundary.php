<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarangayBoundary extends Model
{
    protected $primaryKey = 'psgc_code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'psgc_code', 'barangay_id', 'barangay_code', 'barangay_name', 'city_name',
        'geometry_json', 'source_url', 'source_note',
    ];

    protected function casts(): array
    {
        return ['geometry_json' => 'array'];
    }
}
