<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurokBoundary extends Model
{
    protected $table = 'purok_boundaries';

    protected $primaryKey = 'purok_id';

    public $incrementing = false;

    protected $fillable = [
        'purok_id',
        'geometry_json',
        'source',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function purok(): BelongsTo
    {
        return $this->belongsTo(Purok::class, 'purok_id', 'purok_id');
    }
}
