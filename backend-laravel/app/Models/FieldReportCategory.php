<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldReportCategory extends Model
{
    protected $table = 'field_report_categories';

    protected $primaryKey = 'category_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'category_id',
        'category_key',
        'category_label',
    ];
}
