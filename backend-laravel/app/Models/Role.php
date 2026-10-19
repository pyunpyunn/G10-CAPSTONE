<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $primaryKey = 'role_id';

    public $timestamps = false;

    protected $fillable = [
        'role_key',
        'role_name', 'base_role_key', 'web_modules', 'can_manage_personnel', 'can_approve_requests',
    ];

    protected function casts(): array { return ['web_modules' => 'array', 'can_manage_personnel' => 'boolean', 'can_approve_requests' => 'boolean']; }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id', 'role_id');
    }
}


