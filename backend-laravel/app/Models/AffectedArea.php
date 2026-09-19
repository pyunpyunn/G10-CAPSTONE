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
    protected $fillable = ['disaster_id', 'confirmed_by_admin_id', 'severity_id', 'purok_id', 'sitio_id', 'barangay_id', 'confirmed_by_hq', 'confirmed_at'];
    protected function casts(): array { return ['confirmed_by_hq' => 'boolean', 'confirmed_at' => 'datetime']; }
    public function disaster(): BelongsTo { return $this->belongsTo(DisasterEvent::class, 'disaster_id', 'event_id'); }
    public function severity(): BelongsTo { return $this->belongsTo(SeverityLevel::class, 'severity_id', 'severity_id'); }
}
