<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class VulnerableGroup extends Model
{
    protected $table = 'vulnerable_groups'; protected $primaryKey = 'vulnerable_group_id'; public $incrementing = false; public $timestamps = false;
    protected $fillable = ['vulnerable_group_key', 'vulnerable_group_label'];
    public function members(): BelongsToMany { return $this->belongsToMany(HouseholdMember::class, 'member_vulnerable_groups', 'vulnerable_group_id', 'member_id'); }
}
