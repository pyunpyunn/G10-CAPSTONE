<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barangay extends Model
{
    protected $primaryKey = 'barangay_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['barangay_code', 'barangay_name', 'city_id'];
}
