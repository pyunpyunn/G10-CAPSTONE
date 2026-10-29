<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResponderCommunicationLog extends Model
{
    protected $table = 'responder_communication_logs';

    protected $primaryKey = 'communication_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'communication_id',
        'responder_id',
        'team_id',
        'team_name',
        'disaster_id',
        'message',
        'timestamp',
    ];

    protected function casts(): array
    {
        return [
            'timestamp' => 'datetime',
        ];
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(Responder::class, 'responder_id', 'responder_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(RescueTeam::class, 'team_id', 'team_id');
    }
}
