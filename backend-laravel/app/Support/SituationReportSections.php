<?php

namespace App\Support;

class SituationReportSections
{
    public const VERSION = '4.0';
    public const LABELS = [
        'I'=>'Situation Overview',
        'II'=>'Status of Affected Areas and Population',
        'IV'=>'Damage Assessment',
        'V'=>'Search, Rescue and Retrieval Operations',
        'VI'=>'Status of Evacuation Centers',
        'VII'=>'Resources Status',
    ];
    // Retain partner selection identifiers; display numbering remains consecutive.
    public const NUMBERS = ['I'=>'I','II'=>'II','IV'=>'III','V'=>'IV','VI'=>'V','VII'=>'VI'];
    public const LETTERHEAD = ['Republic of the Philippines', 'Province of Cebu', 'City of Cebu', 'Barangay Mambaling', 'Barangay Disaster Risk Reduction and Management Committee (BDRRMC) – Command Center'];
    public const NOTICE = 'Contains personal data. Handle under RA 10173 (Data Privacy Act).';
    public const INPUTS = ['overview_text','damage_text','response_text','resources_text'];
}
