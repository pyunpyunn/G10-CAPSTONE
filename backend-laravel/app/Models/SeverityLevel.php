<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeverityLevel extends Model
{
    protected $table = 'severity_levels';
    protected $primaryKey = 'severity_id';
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = ['severity_key', 'severity_label'];

    public function disasters(): HasMany
    {
        return $this->hasMany(DisasterEvent::class, 'severity_level_id', 'severity_id');
    }
}
