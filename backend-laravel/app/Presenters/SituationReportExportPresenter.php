<?php

namespace App\Presenters;

class SituationReportExportPresenter
{
    public function rows(array $summary): array
    {
        $r=app(SituationReportDocument::class)->data($summary);
        return app(\App\Services\Web\SituationReportSpreadsheet::class)->rows($r);
    }
}
