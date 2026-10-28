<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamBase extends Model
{
    protected $table = 'rescue_teams';
    protected $primaryKey = 'team_id';
    public $incrementing = false;
    public $timestamps = false;

    protected static function booted(): void
    {
        static::addGlobalScope('configured_base', fn ($query) => $query->whereNotNull('base_latitude')->whereNotNull('base_longitude'));
    }

    public function getNameAttribute(): ?string { return $this->attributes['base_name'] ?? null; }
    public function setNameAttribute($value): void { $this->attributes['base_name'] = $value; }
    public function getLatAttribute(): mixed { return $this->attributes['base_latitude'] ?? null; }
    public function setLatAttribute($value): void { $this->attributes['base_latitude'] = $value; }
    public function getLngAttribute(): mixed { return $this->attributes['base_longitude'] ?? null; }
    public function setLngAttribute($value): void { $this->attributes['base_longitude'] = $value; }

    protected $fillable = ['team_id', 'name', 'lat', 'lng'];

    protected function casts(): array
    {
        return ['lat' => 'decimal:7', 'lng' => 'decimal:7'];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(RescueTeam::class, 'team_id', 'team_id');
    }
}