<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResourceRequest extends Model
{
    protected $table = 'resource_requests';

    protected $primaryKey = 'request_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'request_id',
        'request_source',
        'source_reference',
        'request_category',
        'evacuation_center_id',
        'requested_by',
        'handled_by',
        'resource_type',
        'item_name',
        'quantity',
        'unit',
        'description',
        'urgency_id',
        'status_id',
        'validation_status',
        'validation_notes',
        'validated_by_user_id',
        'validated_at',
        'released_for_tracking_at',
        'tracking_reference',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'urgency_id' => 'integer',
            'status_id' => 'integer',
            'validated_at' => 'datetime',
            'released_for_tracking_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ResourceRequestStatus::class, 'status_id', 'status_id');
    }

    public function validationHistory(): HasMany
    {
        return $this->hasMany(RequestValidation::class, 'request_id', 'request_id');
    }
}