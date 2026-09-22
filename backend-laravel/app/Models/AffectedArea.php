<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffectedArea extends Model
{
    protected $table = 'affected_areas';
    protected $primaryKey = 'affected_area_id';
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = [
        'area_name',
        'disaster_id',
        'confirmed_by_admin_id',
        'severity_id',
        'purok_id',
        'sitio_id',
        'barangay_id',
        'description',
        'hazard_type',
        'latitude',
        'longitude',
        'boundary_geojson',
        'status',
        'confirmed_by_hq',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_by_hq' => 'boolean',
            'confirmed_at' => 'datetime',
            'boundary_geojson' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function disaster(): BelongsTo { return $this->belongsTo(DisasterEvent::class, 'disaster_id', 'event_id'); }
    public function severity(): BelongsTo { return $this->belongsTo(SeverityLevel::class, 'severity_id', 'severity_id'); }
    public function purok(): BelongsTo { return $this->belongsTo(Purok::class, 'purok_id', 'purok_id'); }
    public function sitio(): BelongsTo { return $this->belongsTo(Sitio::class, 'sitio_id', 'sitio_id'); }
    public function barangay(): BelongsTo { return $this->belongsTo(Barangay::class, 'barangay_id', 'barangay_id'); }
}
