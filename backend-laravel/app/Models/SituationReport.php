<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SituationReport extends Model
{
    protected $table = 'situation_reports';

    protected $primaryKey = 'sit_rep_id';

    public $timestamps = false;

    protected $fillable = [
        'report_number',
        'disaster_id',
        'created_by_admin_id',
        'household_id',
        'summary',
        'report_status',
        'reviewed_by_user_id',
        'reviewed_at',
        'escalated_to',
        'is_archived',
        'generated_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'reviewed_at' => 'datetime',
            'generated_at' => 'datetime',
            'archived_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function disaster(): BelongsTo
    {
        return $this->belongsTo(DisasterEvent::class, 'disaster_id', 'event_id');
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id', 'household_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id', 'user_id');
    }
}
