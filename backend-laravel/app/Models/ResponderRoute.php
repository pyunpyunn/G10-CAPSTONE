<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResponderRoute extends Model
{
    protected $table = 'responder_routes';

    protected $primaryKey = 'route_id';

    protected $fillable = [
        'assignment_id',
        'route_name',
        'route_status',
        'start_latitude',
        'start_longitude',
        'end_latitude',
        'end_longitude',
        'estimated_distance_km',
        'estimated_duration_min',
        'route_polyline',
    ];

    protected function casts(): array
    {
        return [
            'start_latitude' => 'decimal:7',
            'start_longitude' => 'decimal:7',
            'end_latitude' => 'decimal:7',
            'end_longitude' => 'decimal:7',
            'estimated_distance_km' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ResponderAssignment::class, 'assignment_id', 'assignment_id');
    }

    public function coordinates(): HasMany
    {
        return $this->hasMany(RouteCoordinate::class, 'route_id', 'route_id');
    }
}
