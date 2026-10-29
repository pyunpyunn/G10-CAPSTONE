<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResqOrphanReference extends Model
{
    protected $table = 'resq_orphan_references';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['preserved_at' => 'datetime'];
    }
}
