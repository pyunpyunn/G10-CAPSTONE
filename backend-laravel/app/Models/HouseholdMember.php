<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HouseholdMember extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'member_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['household_id', 'first_name', 'middle_name', 'last_name', 'birth_date', 'gender_id', 'relationship_id', 'civil_status_id', 'occupation', 'education_level_id', 'is_graduate', 'is_pwd', 'is_senior', 'is_pregnant'];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'is_graduate' => 'boolean', 'is_pwd' => 'boolean', 'is_senior' => 'boolean', 'is_pregnant' => 'boolean'];
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
}
