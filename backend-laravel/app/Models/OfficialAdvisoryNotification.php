<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficialAdvisoryNotification extends Model
{
    protected $table = 'official_advisory_notifications';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'source',
        'fingerprint',
        'title',
        'body',
        'source_url',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
