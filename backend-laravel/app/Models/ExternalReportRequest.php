<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalReportRequest extends Model
{
    protected $table = 'external_report_requests';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'source_system',
        'reference',
        'event_id',
        'sections',
        'format',
        'purpose',
        'status',
        'decision_note',
        'decided_by',
        'decided_at',
        'approved_summary',
        'file',
        'created_at',
        'updated_at',
        'api_version',
    ];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'decided_at' => 'datetime',
            'approved_summary' => 'array',
            'file' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(DisasterEvent::class, 'event_id', 'event_id');
    }
}
