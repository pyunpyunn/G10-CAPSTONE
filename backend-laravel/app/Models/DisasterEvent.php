<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DisasterEvent extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'event_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['name', 'type_id', 'severity_level_id', 'started_at', 'ended_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(DisasterType::class, 'type_id', 'type_id');
    }

    public function householdDisasters(): HasMany
    {
        return $this->hasMany(HouseholdDisaster::class, 'disaster_id', 'event_id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(HouseholdStatusLog::class, 'disaster_id', 'event_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNotNull('started_at')->whereNull('ended_at');
    }
}
