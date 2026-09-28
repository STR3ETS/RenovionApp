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
    | Offertes staat sinds de offerte-editor (briefing v2 §6) standaard aan.
    | Automations blijft bewust uit tot de Nova-rules-sprint; aanzetten kan
    | via MODULE_AUTOMATIONS=true in .env.
    |
    */

    'modules' => [
        'quotes' => (bool) env('MODULE_QUOTES', true),
        'automations' => (bool) env('MODULE_AUTOMATIONS', false),
    ],

];
