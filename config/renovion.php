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
    | Gespreksverslagen (transcriptie)
    |--------------------------------------------------------------------------
    |
    | Opnames van overleggen worden omgezet naar tekst door OpenAI Whisper
    | ("whisper", zoals in Eazyonline2026, sleutel OPENAI_API_KEY) of door
    | AssemblyAI in de EU ("assemblyai", houdt sprekers uit elkaar). Zonder
    | sleutel blijft een opname wachten tot er een sleutel is. "demo" geeft een
    | vast voorbeeldgesprek, alleen voor lokaal testen. De opname zelf wordt na
    | de bewaartermijn verwijderd; het verslag blijft staan (AVG).
    |
    */

    'transcription' => [
        'driver' => env('TRANSCRIPTION_DRIVER', 'whisper'),
        'openai_key' => env('OPENAI_API_KEY'),
        'openai_model' => env('OPENAI_TRANSCRIBE_MODEL', 'whisper-1'),
        'api_key' => env('ASSEMBLYAI_API_KEY'),
        'base_url' => env('ASSEMBLYAI_BASE_URL', 'https://api.eu.assemblyai.com'),
        'speech_models' => array_filter(explode(',', (string) env('ASSEMBLYAI_SPEECH_MODELS', 'universal-3-5-pro,universal-2'))),
        'audio_retention_days' => (int) env('MEETING_AUDIO_RETENTION_DAYS', 90),
        'max_minutes' => (int) env('MEETING_MAX_MINUTES', 180),
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
