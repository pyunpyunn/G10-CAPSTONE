<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResourceRequestStatus extends Model
{
    protected $table = 'resource_request_status';

    protected $primaryKey = 'status_id';

    public $timestamps = false;

    protected $fillable = [
        'status_key',
        'status_label',
    ];

    public function requests(): HasMany
    {
        return $this->hasMany(ResourceRequest::class, 'status_id', 'status_id');
    }
}