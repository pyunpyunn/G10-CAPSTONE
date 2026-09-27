<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BarangayProfile extends Model
{
    use SoftDeletes;

    protected $table = 'barangay_profiles';

    protected $primaryKey = 'profile_id';

    protected $fillable = [
        'barangay_id',
        'display_name',
        'city_name',
        'province_name',
        'office_address',
        'contact_number',
        'email',
        'center_latitude',
        'center_longitude',
        'map_zoom',
        'weather_location_name',
        'weather_latitude',
        'weather_longitude',
        'is_active',
        'configured_by_user_id',
        'map_bounds_json',
    ];

    protected function casts(): array
    {
        return [
            'center_latitude' => 'decimal:7',
            'center_longitude' => 'decimal:7',
            'weather_latitude' => 'decimal:7',
            'weather_longitude' => 'decimal:7',
            'is_active' => 'boolean',
            'map_bounds_json' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class, 'barangay_id', 'barangay_id');
    }

    public function configuredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'configured_by_user_id', 'user_id');
    }
}
