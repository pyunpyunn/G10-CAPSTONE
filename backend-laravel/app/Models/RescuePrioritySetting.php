<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RescuePrioritySetting extends Model
{
    protected $primaryKey = 'version';

    protected $fillable = [
        'impact_weight', 'vulnerability_weight', 'unreported_weight',
        'no_contact_weight', 'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'impact_weight' => 'integer',
            'vulnerability_weight' => 'integer',
            'unreported_weight' => 'integer',
            'no_contact_weight' => 'integer',
        ];
    }
}
