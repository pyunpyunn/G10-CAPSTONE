<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Support\RequestSchema as Schema;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
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
        'member_id',
        'is_active',
        'must_change_password',
        'profile_photo',
        'security_question_1',
        'security_answer_1',
        'security_question_2',
        'security_answer_2',
        'password_changed_at',
    ];

    protected $hidden = [
        'password',
        'temp_password',
        'security_answer_1',
        'security_answer_2',
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
            'must_change_password' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id', 'household_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(HouseholdMember::class, 'member_id', 'member_id');
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
        $roleKey = $loadedRole?->base_role_key ?: ($loadedRole?->role_key ?? ($this->attributes['role'] ?? null));

        if (! $roleKey && isset($this->attributes['role_id'])) {
            $roleKey = match ((int) $this->attributes['role_id']) {
                1 => 'super_admin',
                2, 4 => 'admin',
                3, 5 => 'rescuer',
                6 => 'household_resident',
                default => null,
            };
        }

        return $this->normalizeRoleKey($roleKey);
    }

    public function assignedRoleKey(): ?string
    {
        if (! $this->relationLoaded('role') && ! empty($this->attributes['role_id'])) {
            $this->loadMissing('role');
        }

        $loadedRole = $this->relationLoaded('role') ? $this->getRelation('role') : null;

        return $this->normalizeRoleKey($loadedRole?->role_key ?? ($this->attributes['role'] ?? null));
    }

    private function normalizeRoleKey(mixed $roleKey): ?string
    {
        return match (strtolower((string) $roleKey)) {
            'superadmin', 'super_admin', 'super admin', 'admin', 'operations_admin' => 'operations_admin',
            'hq_admin', 'hq admin', 'pb', 'chair' => 'chair',
            'hq_leader', 'hq leader', 'hqcc_leader', 'owh', 'dsp', 'leo', 'ech', 'response' => 'response',
            'hq_personnel', 'hq personnel' => 'operations_admin',
            'pao' => 'mitigation_prevention',
            'mitigation_prevention' => 'mitigation_prevention',
            'preparedness' => 'preparedness',
            'recovery_rehabilitation' => 'recovery_rehabilitation',
            'tl' => 'tl',
            'tm' => 'tm',
            'rescuer', 'responder' => 'rescuer',
            'household', 'household_resident', 'resident' => 'household_resident',
            default => $roleKey ? strtolower((string) $roleKey) : null,
        };
    }

    public function roleName(): ?string
    {
        $loadedRole = $this->relationLoaded('role') ? $this->getRelation('role') : null;
        $roleName = $loadedRole?->role_name ?? ($this->attributes['role'] ?? null);

        if ($loadedRole && ($loadedRole->base_role_key || $loadedRole->web_modules !== null)) return $loadedRole->role_name;
        return match ($this->assignedRoleKey() ?: $this->roleKey()) {
            'operations_admin' => 'Operations/Admin',
            'chair' => 'BDRRM Committee Chairperson',
            'pb' => 'BDRRM Committee Chairperson',
            'mitigation_prevention' => 'Mitigation and Prevention Sub-committee',
            'preparedness' => 'Preparedness Sub-committee',
            'response' => 'Response Sub-committee',
            'owh', 'dsp', 'leo', 'ech', 'hq_leader' => 'Response Sub-committee',
            'pao' => 'Mitigation and Prevention Sub-committee',
            'recovery_rehabilitation' => 'Recovery and Rehabilitation Sub-committee',
            'tl' => 'Team Leader',
            'tm', 'rescuer' => 'Team Member',
            'household_resident' => 'Household Resident',
            default => $roleName,
        };
    }

    public function isHqccLeader(): bool
    {
        if ($this->is_active === false || ($this->attributes['deleted_at'] ?? null)
            || ! Schema::hasTable('responders') || ! Schema::hasTable('rescue_teams')) {
            return false;
        }

        return DB::table('responders as responder')
            ->join('rescue_teams as team', 'team.team_id', '=', 'responder.team_id')
            ->where('responder.user_id', $this->getKey())
            ->whereColumn('team.leader_responder_id', 'responder.responder_id')
            ->exists();
    }

    public function canManagePersonnel(): bool
    {
        if ($this->is_active === false || ($this->attributes['deleted_at'] ?? null)) return false;

        $key = $this->roleKey();
        if ($key === 'operations_admin') return true;
        $role = $this->relationLoaded('role') ? $this->getRelation('role') : null;
        if ($role?->can_manage_personnel !== null) {
            $modules = app(\App\Services\Web\WebAccessPolicy::class)->modulesFor($this);
            return (bool) $role->can_manage_personnel && in_array('personnel_access', $modules, true);
        }
        return false;
    }
}
