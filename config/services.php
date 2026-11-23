<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    /*
    | Nova en de agents van Renovion OS. Zonder sleutel staan ze uit en zegt
    | de app dat eerlijk. Het model is in te stellen met RENOVION_AI_MODEL.
    */
    // Token waarmee de Renovion-website aanvragen aanlevert (POST /api/aanvragen, header X-Intake-Token).
    'renovion' => [
        'lead_intake_token' => env('LEAD_INTAKE_TOKEN'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('RENOVION_AI_MODEL', 'claude-opus-5-5'),
        'agents' => (bool) env('RENOVION_AGENTS', true),
    ],

];
