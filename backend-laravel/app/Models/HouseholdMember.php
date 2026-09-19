<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HouseholdMember extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'member_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['household_id', 'first_name', 'middle_name', 'last_name', 'birth_date', 'gender_id', 'relationship_id', 'is_household_head', 'can_report_for_household', 'contact_number', 'civil_status_id', 'occupation', 'education_level_id', 'is_graduate', 'is_pwd', 'is_senior', 'is_pregnant'];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'is_household_head' => 'boolean', 'can_report_for_household' => 'boolean', 'is_graduate' => 'boolean', 'is_pwd' => 'boolean', 'is_senior' => 'boolean', 'is_pregnant' => 'boolean', 'created_at' => 'datetime', 'updated_at' => 'datetime', 'deleted_at' => 'datetime'];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id', 'household_id');
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(Gender::class, 'gender_id', 'gender_id');
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(Relationship::class, 'relationship_id', 'relationship_id');
    }

    public function vulnerableGroups(): BelongsToMany
    {
        return $this->belongsToMany(VulnerableGroup::class, 'member_vulnerable_groups', 'member_id', 'vulnerable_group_id');
    }
}
