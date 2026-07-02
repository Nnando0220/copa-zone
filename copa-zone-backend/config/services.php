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

    'openligadb' => [
        'base_url' => env('OPENLIGADB_BASE_URL', 'https://api.openligadb.de'),
        'timeout' => (int) env('OPENLIGADB_TIMEOUT', 15),
        'connect_timeout' => (int) env('OPENLIGADB_CONNECT_TIMEOUT', 5),
        'source_timezone' => env('OPENLIGADB_SOURCE_TIMEZONE', 'Europe/Berlin'),
        'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'America/Sao_Paulo'),
        'daily_limit' => (int) env('OPENLIGADB_DAILY_LIMIT', 1000),
        'reserved_requests' => (int) env('OPENLIGADB_RESERVED_REQUESTS', 100),
        'world_cup' => [
            'shortcut' => env('OPENLIGADB_WORLD_CUP_SHORTCUT', 'wm26'),
            'season' => (int) env('OPENLIGADB_WORLD_CUP_SEASON', 2026),
        ],
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

];
