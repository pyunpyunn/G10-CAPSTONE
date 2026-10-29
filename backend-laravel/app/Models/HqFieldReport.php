<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HqFieldReport extends Model
{
    protected $table = 'hq_field_reports';

    protected $primaryKey = 'hq_report_id';

    public $timestamps = false;

    protected $fillable = [
        'hq_report_id',
        'disaster_id',
        'household_id',
        'source',
        'linked_report_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function linkedReport(): BelongsTo
    {
        return $this->belongsTo(ResponderFieldReport::class, 'linked_report_id', 'report_id');
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id', 'household_id');
    }
}
