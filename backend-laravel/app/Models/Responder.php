<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Responder extends Model
{
    use SoftDeletes;
    protected $table = 'responders';
    protected $primaryKey = 'responder_id';
    public $incrementing = false;
    protected $fillable = ['user_id', 'responder_code', 'created_by_admin_id', 'team_id', 'username', 'password_hash', 'full_name', 'title', 'contact_number', 'emergency_contact_name', 'emergency_contact_number', 'date_of_birth', 'gender', 'blood_type', 'address', 'skills', 'training_notes', 'certification_reference', 'equipment_notes', 'is_validated', 'is_deployed', 'duty_status', 'last_active_at'];
    protected function casts(): array { return ['date_of_birth' => 'date', 'is_validated' => 'boolean', 'is_deployed' => 'boolean', 'last_active_at' => 'datetime', 'created_at' => 'datetime', 'updated_at' => 'datetime', 'deleted_at' => 'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'user_id', 'user_id'); }
    public function team(): BelongsTo { return $this->belongsTo(RescueTeam::class, 'team_id', 'team_id'); }
    public function assignments(): HasMany { return $this->hasMany(ResponderAssignment::class, 'responder_id', 'responder_id'); }
}
