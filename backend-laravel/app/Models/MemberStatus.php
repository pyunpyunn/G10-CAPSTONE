<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberStatus extends Model
{
    protected $table = 'member_statuses';
    protected $primaryKey = 'status_id';
    public $incrementing = false;
    protected $fillable = ['status_key', 'status_label', 'description', 'severity_rank', 'requires_rescue', 'is_terminal', 'color_hex', 'sort_order', 'is_active'];
    protected function casts(): array { return ['severity_rank' => 'integer', 'requires_rescue' => 'boolean', 'is_terminal' => 'boolean', 'sort_order' => 'integer', 'is_active' => 'boolean', 'created_at' => 'datetime', 'updated_at' => 'datetime']; }
    public function currentStatuses(): HasMany { return $this->hasMany(MemberDisasterStatus::class, 'status_id', 'status_id'); }
}
