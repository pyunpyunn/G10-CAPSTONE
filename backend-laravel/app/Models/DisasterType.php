<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DisasterType extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'type_id';

    public $incrementing = false;

    protected $fillable = ['type_code', 'type_name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
