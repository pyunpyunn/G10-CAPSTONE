<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentArchive extends Model
{
    protected $table = 'incident_archives';

    protected $primaryKey = 'archive_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'archive_id',
        'archive_type',
        'disaster_id',
        'reference_table',
        'reference_id',
        'archived_by_admin_id',
        'archive_note',
        'archived_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function disaster(): BelongsTo
    {
        return $this->belongsTo(DisasterEvent::class, 'disaster_id', 'event_id');
    }
}
