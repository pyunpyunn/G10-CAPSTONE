<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UrgencyLevel extends Model
{
    protected $table = 'urgency_levels';
    protected $primaryKey = 'urgency_id';
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = ['urgency_key', 'urgency_label'];

    public function requests(): HasMany
    {
        return $this->hasMany(ResourceRequest::class, 'urgency_id', 'urgency_id');
    }
}
