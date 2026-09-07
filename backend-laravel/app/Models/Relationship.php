<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Relationship extends Model
{
    protected $primaryKey = 'relationship_id';

    public $timestamps = false;

    protected $fillable = ['relationship_key', 'relationship_label'];
}
