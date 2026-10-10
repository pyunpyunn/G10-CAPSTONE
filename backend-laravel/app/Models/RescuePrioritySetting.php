<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RescuePrioritySetting extends Model
{
    protected $primaryKey = 'version';

    protected $fillable = [
        'impact_weight', 'vulnerability_weight', 'unreported_weight',
        'no_contact_weight', 'updated_by_user_id', 'change_reason',
        'high_percent', 'medium_percent', 'deploy_first_percent',
    ];

    protected function casts(): array
    {
        return [
            'impact_weight' => 'integer',
            'vulnerability_weight' => 'integer',
            'unreported_weight' => 'integer',
            'no_contact_weight' => 'integer',
            'high_percent' => 'float',
            'medium_percent' => 'float',
            'deploy_first_percent' => 'float',
        ];
    }
}
