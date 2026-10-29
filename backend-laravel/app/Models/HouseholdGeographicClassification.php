<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdGeographicClassification extends Model
{
    protected $table = 'household_geographic_classifications';

    protected $primaryKey = 'household_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'household_id',
        'status',
        'latitude',
        'longitude',
        'sitio_id',
        'purok_id',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id', 'household_id');
    }

    public function purok(): BelongsTo
    {
        return $this->belongsTo(Purok::class, 'purok_id', 'purok_id');
    }

    public function sitio(): BelongsTo
    {
        return $this->belongsTo(Sitio::class, 'sitio_id', 'sitio_id');
    }
}
