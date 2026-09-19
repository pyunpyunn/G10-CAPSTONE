<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvacuationRecord extends Model
{
    protected $table = 'evacuation_records';
    protected $primaryKey = 'evacuation_id';
    public $incrementing = false;
    protected $fillable = ['event_id', 'household_id', 'center_id', 'household_status_id', 'evacuated_count', 'method', 'verified_by', 'verified_at'];
    protected function casts(): array { return ['evacuated_count' => 'integer', 'verified_at' => 'datetime', 'created_at' => 'datetime', 'updated_at' => 'datetime']; }
    public function household(): BelongsTo { return $this->belongsTo(Household::class, 'household_id', 'household_id'); }
    public function center(): BelongsTo { return $this->belongsTo(EvacuationCenter::class, 'center_id', 'evacuation_center_id'); }
    public function disaster(): BelongsTo { return $this->belongsTo(DisasterEvent::class, 'event_id', 'event_id'); }
}
