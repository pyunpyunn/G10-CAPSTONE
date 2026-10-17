<?php

namespace App\Services\Shared;

class TrackingAidReportClient extends EvaTrackReportClient
{
    public function __construct()
    {
        parent::__construct('trackingaid_reports', 'TrackingAid', 'trackingaid');
    }
}