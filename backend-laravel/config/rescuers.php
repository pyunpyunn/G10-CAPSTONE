<?php

return [
    // Canonical account/team values shared by validation and API presentation.
    'duty_statuses' => [
        'available' => ['label' => 'Available', 'tone' => 'green'],
        'on_duty' => ['label' => 'On duty', 'tone' => 'green'],
        'reserve' => ['label' => 'Reserve', 'tone' => 'blue'],
        'off_duty' => ['label' => 'Off duty', 'tone' => 'gray'],
        'unavailable' => ['label' => 'Unavailable', 'tone' => 'amber'],
        'dispatched' => ['label' => 'Dispatched', 'tone' => 'purple'],
        'on_scene' => ['label' => 'On-scene', 'tone' => 'green'],
        'disabled' => ['label' => 'Disabled', 'tone' => 'gray'],
    ],
    'account_statuses' => [
        'active' => ['label' => 'Active', 'tone' => 'green'],
        'reserve' => ['label' => 'Reserve', 'tone' => 'blue'],
        'disabled' => ['label' => 'Disabled', 'tone' => 'gray'],
    ],
    'team_duty_statuses' => ['available', 'on_duty', 'off_duty', 'unavailable'],
    'account_defaults' => [
        'account_status' => 'active', 'duty_status' => 'available',
        'blood_type' => 'Unknown', 'title' => 'Responder',
    ],
    'team_defaults' => [
        'team_id' => null, 'team_code' => '', 'team_name' => '', 'team_type' => '',
        'duty_status' => 'available', 'assigned_purok_id' => null,
        'leader_responder_id' => null, 'member_ids' => [],
    ],
    'members_per_page' => 7,
];
