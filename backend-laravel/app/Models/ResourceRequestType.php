<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResourceRequestType extends Model
{
    protected $table = 'resource_request_types';

    protected $primaryKey = 'type_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'type_key',
        'type_label',
        'category',
        'resource_type',
        'item_name',
        'quantity',
        'unit',
        'storage_depot',
        'exp_date',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'exp_date' => 'date',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ResourceRequest::class, 'resource_type_id', 'type_id');
    }
}
