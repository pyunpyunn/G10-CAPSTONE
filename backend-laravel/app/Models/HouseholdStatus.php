<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HouseholdStatus extends Model
{
    public const SAFE_KEYS = ['active', 'returned', 'safe', 'evacuated', 'relocated'];

    public const EVACUATED_KEYS = ['evacuated', 'relocated'];

    public const UNSAFE_KEYS = ['not_evacuated', 'displaced', 'unsafe', 'needs_help', 'need_help', 'needs_assistance', 'missing', 'injured', 'trapped', 'unreachable', 'deceased'];

    protected $primaryKey = 'status_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['status_key', 'status_label', 'severity_rank', 'requires_rescue', 'color_hex', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['severity_rank' => 'integer', 'requires_rescue' => 'boolean', 'sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(HouseholdStatusLog::class, 'status_id', 'status_id');
    }
}
