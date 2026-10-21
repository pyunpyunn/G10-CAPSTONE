<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrgencyLevel extends Model
{
    protected $table = 'urgency_levels';
    protected $primaryKey = 'urgency_id';
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = ['urgency_key', 'urgency_label'];

}


