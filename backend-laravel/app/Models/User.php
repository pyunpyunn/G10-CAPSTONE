<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Support\RequestSchema as Schema;
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
        'temp_password',
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

    public function isHqccLeader(): bool
    {
        if ($this->is_active === false || ($this->attributes['deleted_at'] ?? null)) return false;
        foreach (['responders' => ['user_id', 'team_id', 'responder_id'], 'rescue_teams' => ['team_id', 'team_code', 'team_type', 'leader_responder_id']] as $table => $columns) {
            if (! Schema::hasTable($table)) return false;
            foreach ($columns as $column) if (! Schema::hasColumn($table, $column)) return false;
        }

        return \Illuminate\Support\Facades\DB::table('rescue_teams as t')
            ->join('responders as r', 'r.responder_id', '=', 't.leader_responder_id')
            ->whereColumn('r.team_id', 't.team_id')
            ->where('r.user_id', $this->getKey())
            ->when(Schema::hasColumn('responders', 'deleted_at'), fn ($query) => $query->whereNull('r.deleted_at'))
            ->where(fn ($query) => $query->whereRaw('UPPER(t.team_code) = ?', ['HQCC'])
                ->orWhereRaw('UPPER(t.team_type) IN (?, ?, ?)', ['HQ', 'HQCC', 'HEADQUARTERS']))
            ->exists();
    }

    public function canManagePersonnel(): bool
    {
        if ($this->is_active === false || ($this->attributes['deleted_at'] ?? null)) return false;

        return in_array($this->roleKey(), ['super_admin', 'admin'], true) || $this->isHqccLeader();
    }
}


