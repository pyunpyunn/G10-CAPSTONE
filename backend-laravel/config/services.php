<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'osrm' => [
        'base_url' => env('OSRM_BASE_URL', 'https://router.project-osrm.org'),
        'ssl_verify' => env('OSRM_SSL_VERIFY', false),
    ],

    'onesignal' => [
        'app_id' => env('ONESIGNAL_APP_ID'),
        'api_key' => env('ONESIGNAL_API_KEY'),
        'base_url' => env('ONESIGNAL_BASE_URL', 'https://api.onesignal.com'),
    ],

    'trackingaid' => [
        'connection' => env('TRACKINGAID_DB_CONNECTION_NAME', 'trackingaid'),
        'forward_table' => env('TRACKINGAID_FORWARD_TABLE', 'resqperation_forwarded_requests'),
    ],

    'external_request_intake' => [
        'key' => env('EXTERNAL_REQUEST_INTAKE_KEY'),
    ],

];
