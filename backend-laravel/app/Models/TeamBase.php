<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamBase extends Model
{
    protected $table = 'team_bases';
    protected $primaryKey = 'team_id';
    public $incrementing = false;
    public $timestamps = false;

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