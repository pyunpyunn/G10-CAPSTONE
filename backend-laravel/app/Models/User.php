<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'first_name',
        'last_name',
        'username',
        'login_id',
        'name',
        'email',
        'password',
        'role_id',
        'role',
        'contact_number',
        'assigned_center_id',
        'household_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    public function getKeyName(): string
    {
        return Schema::hasColumn($this->getTable(), 'user_id') ? 'user_id' : 'id';
    }

    public function getFullNameAttribute(): string
    {
        $fullName = trim(($this->attributes['first_name'] ?? '').' '.($this->attributes['last_name'] ?? ''));

        return $fullName !== '' ? $fullName : ($this->attributes['name'] ?? '');
    }

    public function getUsernameAttribute($value): ?string
    {
        return $value ?: ($this->attributes['login_id'] ?? null);
    }

    public function roleKey(): ?string
    {
        if (! $this->relationLoaded('role') && ! empty($this->attributes['role_id'])) {
            $this->loadMissing('role');
        }

        $loadedRole = $this->relationLoaded('role') ? $this->getRelation('role') : null;
        $roleKey = $loadedRole?->role_key ?? ($this->attributes['role'] ?? null);

        if (! $roleKey && isset($this->attributes['role_id'])) {
            $roleKey = match ((int) $this->attributes['role_id']) {
                1 => 'super_admin',
                2, 4 => 'admin',
                3, 5 => 'rescuer',
                6 => 'household_resident',
                default => null,
            };
        }

        return match (strtolower((string) $roleKey)) {
            'super_admin', 'super admin' => 'super_admin',
            'admin', 'hq_admin', 'hq admin' => 'admin',
            'rescuer', 'responder' => 'rescuer',
            'household', 'household_resident', 'resident' => 'household_resident',
            default => $roleKey,
        };
    }

    public function roleName(): ?string
    {
        $loadedRole = $this->relationLoaded('role') ? $this->getRelation('role') : null;
        $roleName = $loadedRole?->role_name ?? ($this->attributes['role'] ?? null);

        return match ($this->roleKey()) {
            'super_admin' => 'Super Admin',
            'admin' => 'Admin',
            'rescuer' => 'Rescuer',
            'household_resident' => 'Household Resident',
            default => $roleName,
        };
    }
}
