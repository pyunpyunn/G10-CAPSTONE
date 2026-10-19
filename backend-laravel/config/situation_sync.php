<?php

return [
    'enabled'=>(bool) env('SITREP_AUTO_SYNC',true),
    'fresh_seconds'=>60,
    'evatrack_report_type'=>env('SITREP_EVATRACK_REPORT_TYPE','general'),
    'trackingaid_report_type'=>env('SITREP_TRACKINGAID_REPORT_TYPE','general'),
];
