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
    | Aanvraagmail
    |--------------------------------------------------------------------------
    |
    | Token waarmee de maildienst berichten voor aanvraag@ mag afleveren op
    | POST /api/inkomende-mail, in de header X-Inbound-Token of als ?token=.
    | Zonder token staat die ingang dicht.
    |
    */

    'inbound_mail_token' => env('INBOUND_MAIL_TOKEN'),

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

    /*
    |--------------------------------------------------------------------------
    | Offertetool
    |--------------------------------------------------------------------------
    |
    | De tool (resources/offer-tool/tool.html) bevat in zijn broncode nog de
    | voorbeeldgegevens van één project. Zolang dat zo is, hoort er maar één
    | offerte in: een klant van een ander project zou die gegevens anders in
    | de broncode kunnen lezen. Staat er een opgeschoonde tool, zet dan
    | OFFER_TOOL_MULTI=true om meer offertes toe te staan.
    |
    */

    'offer_tool' => [
        'multi_project' => (bool) env('OFFER_TOOL_MULTI', false),
        'email' => env('OFFER_TOOL_EMAIL', 'info@renoviongroep.nl'),
        // Inlogpogingen per kwartier, per IP-adres en per offerte.
        'max_attempts' => 5,
        'decay_seconds' => 900,
        'max_upload_kb' => 20480,
    ],

];
