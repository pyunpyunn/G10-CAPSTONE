<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapTileMetadata extends Model
{
    protected $table = 'map_tile_metadata';

    protected $primaryKey = 'tile_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'tile_id',
        'zoom_level',
        'tile_x',
        'tile_y',
        'tile_url',
        'cached_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'cached_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
