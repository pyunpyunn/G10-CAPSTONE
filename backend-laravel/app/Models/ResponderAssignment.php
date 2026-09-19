<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResponderAssignment extends Model
{
    protected $table = 'responder_assignments';
    protected $primaryKey = 'assignment_id';
    public $incrementing = false;
    protected $fillable = ['assignment_code', 'responder_id', 'team_id', 'disaster_id', 'affected_area_id', 'household_id', 'status_log_id', 'assigned_area', 'route_notes', 'priority_level', 'dispatch_notes', 'created_by_admin_id', 'status', 'assigned_at', 'accepted_at', 'en_route_at', 'arrived_at', 'completed_at', 'outcome_notes'];
    protected function casts(): array { return ['assigned_at' => 'datetime', 'accepted_at' => 'datetime', 'en_route_at' => 'datetime', 'arrived_at' => 'datetime', 'completed_at' => 'datetime', 'created_at' => 'datetime', 'updated_at' => 'datetime']; }
    public function responder(): BelongsTo { return $this->belongsTo(Responder::class, 'responder_id', 'responder_id'); }
    public function team(): BelongsTo { return $this->belongsTo(RescueTeam::class, 'team_id', 'team_id'); }
    public function disaster(): BelongsTo { return $this->belongsTo(DisasterEvent::class, 'disaster_id', 'event_id'); }
    public function household(): BelongsTo { return $this->belongsTo(Household::class, 'household_id', 'household_id'); }
    public function affectedArea(): BelongsTo { return $this->belongsTo(AffectedArea::class, 'affected_area_id', 'affected_area_id'); }
}
