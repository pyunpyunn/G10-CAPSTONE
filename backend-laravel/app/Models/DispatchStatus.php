<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispatchStatus extends Model
{
    protected $table = 'dispatch_statuses';

    protected $primaryKey = 'status_key';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'status_key',
        'label',
        'is_open',
        'allow_create',
        'allow_mobile',
        'dashboard_group',
        'is_alias',
        'is_active',
        'sort_order',
        'status_code',
    ];

    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'allow_create' => 'boolean',
            'allow_mobile' => 'boolean',
            'is_alias' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
