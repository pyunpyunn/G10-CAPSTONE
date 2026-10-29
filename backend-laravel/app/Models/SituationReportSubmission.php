<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A manual handoff log; creating this record never transmits a report. */
class SituationReportSubmission extends Model
{
    protected $fillable=['sit_rep_id','submitted_at','delivered_by','received_by','recorded_by'];
    protected function casts(): array { return ['submitted_at'=>'datetime']; }

    public function sitRep(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\SituationReport::class, 'sit_rep_id', 'sit_rep_id');
    }
}
