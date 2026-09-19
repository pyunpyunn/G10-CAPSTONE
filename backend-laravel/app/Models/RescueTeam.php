<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RescueTeam extends Model
{
    protected $table = 'rescue_teams';
    protected $primaryKey = 'team_id';
    public $incrementing = false;
    protected $fillable = ['team_code', 'team_name', 'team_type', 'assigned_purok_id', 'leader_responder_id', 'duty_status'];
    protected function casts(): array { return ['created_at' => 'datetime', 'updated_at' => 'datetime']; }
    public function responders(): HasMany { return $this->hasMany(Responder::class, 'team_id', 'team_id'); }
    public function assignments(): HasMany { return $this->hasMany(ResponderAssignment::class, 'team_id', 'team_id'); }
}
