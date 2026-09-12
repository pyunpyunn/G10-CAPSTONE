<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purok extends Model
{
    protected $primaryKey = 'purok_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['purok_name', 'sitio_id'];
}
