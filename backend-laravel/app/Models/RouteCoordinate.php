<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteCoordinate extends Model
{
    protected $table = 'route_coordinates';

    protected $primaryKey = 'coordinate_id';

    public $timestamps = false;

    protected $fillable = [
        'route_id',
        'latitude',
        'longitude',
        'sequence_order',
        'recorded_at',
        'accuracy_m',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'sequence_order' => 'integer',
            'recorded_at' => 'datetime',
            'accuracy_m' => 'decimal:2',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(ResponderRoute::class, 'route_id', 'route_id');
    }
}
