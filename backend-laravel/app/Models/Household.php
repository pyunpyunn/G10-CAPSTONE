<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Household extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'household_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['household_code', 'household_number', 'household_name', 'email', 'created_by', 'contact_number', 'emergency_contact', 'member_count', 'address_id'];

    protected function casts(): array
    {
        return ['member_count' => 'integer'];
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'address_id', 'address_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(HouseholdMember::class, 'household_id', 'household_id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(HouseholdStatusLog::class, 'household_id', 'household_id')->latest('submitted_at');
    }

    public function disasterSnapshots(): HasMany
    {
        return $this->hasMany(HouseholdDisaster::class, 'household_id', 'household_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(DeviceToken::class, 'household_id', 'household_id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $term === '' ? $query : $query->where(fn (Builder $q) => $q->where('household_name', 'like', "%{$term}%")->orWhere('household_id', 'like', "%{$term}%")->orWhere('household_code', 'like', "%{$term}%")->orWhere('contact_number', 'like', "%{$term}%"));
    }
}
