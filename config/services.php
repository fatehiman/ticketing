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

    // SMS gateway (template based). See App\Sms\MsgwayClient.
    'msgway' => [
        'key' => env('MSGWAY_API_KEY'),
        'url' => env('MSGWAY_URL', 'https://api.msgway.com'),
        'timeout' => (int) env('MSGWAY_TIMEOUT', 10),
        // Voice call (IVR) route: its own template and a provider number.
        'ivr_template' => (int) env('MSGWAY_IVR_TEMPLATE', 2),
        'ivr_provider' => (int) env('MSGWAY_IVR_PROVIDER', 1),
    ],

    // Public profile pictures by email (App\Support\PublicAvatar). Off in tests.
    'avatar_lookup' => [
        'enabled' => (bool) env('AVATAR_LOOKUP', true),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
