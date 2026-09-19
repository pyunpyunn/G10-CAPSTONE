<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EvacuationCenter extends Model
{
    use SoftDeletes;
    protected $table = 'evacuation_centers';
    protected $primaryKey = 'evacuation_center_id';
    public $incrementing = false;
    protected $fillable = ['current_event_id', 'name', 'center_type', 'latitude', 'longitude', 'capacity', 'status', 'current_occupancy', 'contact_person', 'contact_number', 'osm_address', 'notes'];
    protected function casts(): array { return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'capacity' => 'integer', 'current_occupancy' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime', 'deleted_at' => 'datetime']; }
    public function units(): HasMany { return $this->hasMany(AccommodationUnit::class, 'center_id', 'evacuation_center_id'); }
    public function records(): HasMany { return $this->hasMany(EvacuationRecord::class, 'center_id', 'evacuation_center_id'); }
}
