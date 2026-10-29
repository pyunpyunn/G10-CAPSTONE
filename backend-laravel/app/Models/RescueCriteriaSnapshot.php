<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RescueCriteriaSnapshot extends Model
{
    protected $table = 'rescue_criteria_snapshots';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'event_id',
        'observed_at',
        'impact',
        'special_needs',
        'unreported',
        'no_contact',
        'purok_count',
        'rescue_priority_version',
    ];

    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
        ];
    }
}
