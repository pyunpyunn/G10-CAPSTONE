<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DisasterType extends Model
{

    protected $primaryKey = 'type_id';

    public $incrementing = false;

    protected $fillable = ['type_code', 'type_name', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function severityLevels(): BelongsToMany
    {
        return $this->belongsToMany(
            SeverityLevel::class,
            'disaster_type_severity_levels',
            'type_id',
            'severity_id',
            'type_id',
            'severity_id',
        );
    }
}

