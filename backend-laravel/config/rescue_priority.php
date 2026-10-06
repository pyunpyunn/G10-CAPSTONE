<?php

return [
    'high_percent' => (float) env('RESCUE_PRIORITY_HIGH_PERCENT', 40),
    'medium_percent' => (float) env('RESCUE_PRIORITY_MEDIUM_PERCENT', 20),
    'deploy_first_percent' => (float) env('RESCUE_PRIORITY_DEPLOY_FIRST_PERCENT', 20),
];
