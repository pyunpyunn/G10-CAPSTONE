<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gender extends Model
{
    protected $primaryKey = 'gender_id';

    public $timestamps = false;

    protected $fillable = ['gender_key', 'gender_label'];
}
