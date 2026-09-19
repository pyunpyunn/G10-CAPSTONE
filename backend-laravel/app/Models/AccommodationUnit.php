<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccommodationUnit extends Model
{
    use SoftDeletes;

    protected $table = 'accommodation_units';

    protected $primaryKey = 'unit_id';

    protected $fillable = [
        'center_id',
        'name',
        'type_id',
        'max_capacity',
    ];

    protected function casts(): array
    {
        return [
            'type_id' => 'integer',
            'max_capacity' => 'integer',
            'created_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AccommodationType::class, 'type_id', 'type_id');
    }
}
