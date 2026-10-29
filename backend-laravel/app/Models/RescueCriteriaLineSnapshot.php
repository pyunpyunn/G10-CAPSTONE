<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RescueCriteriaLineSnapshot extends Model
{
    protected $table = 'rescue_criteria_line_snapshots';

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'event_id',
        'bucket_hour',
        'observed_at',
        'impact_open',
        'household_total',
        'special_open',
        'vulnerable_total',
        'unreported_open',
        'member_total',
        'no_contact_open',
        'no_contact_total',
        'created_at',
        'updated_at',
        'rescue_priority_version',
    ];

    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
