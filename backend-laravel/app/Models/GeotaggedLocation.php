<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeotaggedLocation extends Model
{
    protected $table = 'geotagged_locations';

    protected $primaryKey = 'location_id';

    public $timestamps = true;

    protected $fillable = [
        'household_id',
        'latitude',
        'longitude',
        'location_label',
        'accuracy_m',
        'geotag_source',
        'is_verified',
        'verified_by_user_id',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy_m' => 'decimal:2',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id', 'household_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id', 'user_id');
    }
}
