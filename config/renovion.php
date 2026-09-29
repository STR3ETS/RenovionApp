<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lead intake token
    |--------------------------------------------------------------------------
    |
    | Token waarmee de Renovion-website (of andere bronnen) nieuwe aanvragen
    | mag aanleveren via POST /api/aanvragen met de header X-Intake-Token.
    |
    */

    'lead_intake_token' => env('LEAD_INTAKE_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Nova AI-assistent
    |--------------------------------------------------------------------------
    |
    | Nova gebruikt de Anthropic Claude API voor intentherkenning.
    | Acties worden nooit uitgevoerd zonder bevestiging van de gebruiker.
    |
    */

    'ai' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('RENOVION_AI_MODEL', 'claude-sonnet-5'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Modules
    |--------------------------------------------------------------------------
    |
    | Beide modules staan standaard aan: Offertes sinds de offerte-editor
    | (briefing §6), Automations sinds de Nova-rules-sprint (briefing §12,
    | per regel instelbaar). Uitzetten kan via .env.
    |
    */

    'modules' => [
        'quotes' => (bool) env('MODULE_QUOTES', true),
        'automations' => (bool) env('MODULE_AUTOMATIONS', true),
    ],

];
