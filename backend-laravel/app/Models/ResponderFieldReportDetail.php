<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResponderFieldReportDetail extends Model
{
    protected $table = 'responder_field_report_details';

    protected $primaryKey = 'detail_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'detail_id',
        'report_id',
        'category_id',
        'value',
        'notes',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ResponderFieldReport::class, 'report_id', 'report_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FieldReportCategory::class, 'category_id', 'category_id');
    }
}
