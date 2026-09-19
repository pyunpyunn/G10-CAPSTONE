<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Relationship extends Model
{
    protected $primaryKey = 'relationship_id';

    public $timestamps = false;

    protected $fillable = ['relationship_key', 'relationship_label', 'is_head', 'is_immediate_family', 'can_report_for_household', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_head' => 'boolean', 'is_immediate_family' => 'boolean', 'can_report_for_household' => 'boolean', 'sort_order' => 'integer', 'is_active' => 'boolean', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    }
}
