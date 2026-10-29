<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfflinePack extends Model
{
    protected $table = 'offline_packs';

    protected $primaryKey = 'version';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'version',
        'bbox',
        'style_url',
        'size_bytes',
        'checksum',
        'is_active',
        'created_at',
        'updated_at',
        'pack_format',
    ];

    protected function casts(): array
    {
        return [
            'bbox' => 'array',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
