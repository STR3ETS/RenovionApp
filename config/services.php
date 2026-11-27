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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.eu.mailgun.net'),
        'scheme' => 'https',
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
    | Nova en de agents van Renovion Groep. Zonder sleutel staan ze uit en zegt
    | de app dat eerlijk. Het model is in te stellen met RENOVION_AI_MODEL.
    */
    // Token waarmee de Renovion-website aanvragen aanlevert (POST /api/aanvragen, header X-Intake-Token).
    // mail_name en reply_to: zo komt een uitnodiging binnen, ook als het verzenddomein van een ander is.
    'renovion' => [
        'lead_intake_token' => env('LEAD_INTAKE_TOKEN'),
        'mail_name' => env('RENOVION_MAIL_NAME', 'Renovion Groep BV'),
        'reply_to' => env('RENOVION_REPLY_TO', 'info@renovion.nl'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('RENOVION_AI_MODEL', 'claude-opus-5-5'),
        'agents' => (bool) env('RENOVION_AGENTS', true),
    ],

];
