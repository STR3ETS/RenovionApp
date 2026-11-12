<?php

use App\Http\Controllers\Api\App\SessionController;

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/app/*'],

    'allowed_methods' => ['*'],

    // De iOS/Android-app draait op een eigen adres (mobile/app); alleen dat mag de app-API aanroepen.
    'allowed_origins' => SessionController::APP_ORIGINS,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    // Hoe lang de app het antwoord op de CORS-controle (OPTIONS) mag onthouden. Op 0 ging vóór elk verzoek
    // een extra OPTIONS-verzoek (±0,3 s); Chromium (Android-WebView) onthoudt het hoogstens 2 uur.
    'max_age' => 7200,

    'supports_credentials' => false,

];
