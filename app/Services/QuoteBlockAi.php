<?php

namespace App\Services;

use Anthropic\Client;
use App\Models\Quote;
use App\Support\QuoteBlockRegistry;

/**
 * Per-blok AI voor de offerte-builder (EasyDash-concept): herschrijft of
 * vult één blok, met de klant- en offertecontext. De structuur (sleutels)
 * van het blok blijft altijd intact; de medewerker controleert het resultaat
 * in het live document.
 */
class QuoteBlockAi
{
    public function isConfigured(): bool
    {
        return filled(config('renovion.ai.api_key'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, data?: array<string, mixed>, error?: string}
     */
    public function rewrite(Quote $quote, string $type, array $data, string $instruction = ''): array
    {
        $meta = QuoteBlockRegistry::registry()[$type] ?? null;

        if ($meta === null) {
            return ['ok' => false, 'error' => 'Onbekend bloktype.'];
        }

        $shape = $meta['default'] ?? [];
        $jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        $client = new Client(apiKey: (string) config('renovion.ai.api_key'));

        $response = $client->messages->create(
            model: (string) config('renovion.ai.model'),
            maxTokens: 2000,
            outputConfig: ['effort' => 'low'],
            system: [[
                'type' => 'text',
                'text' => $this->systemPrompt($quote, $meta, json_encode($shape, $jsonFlags)),
            ]],
            messages: [[
                'role' => 'user',
                'content' => "Huidige inhoud van het blok:\n".json_encode($data ?: $shape, $jsonFlags)."\n\n"
                    .($instruction !== ''
                        ? "Wat de medewerker wil: {$instruction}\n\nPas het blok hierop aan en geef de nieuwe JSON terug."
                        : 'Verbeter en vul dit blok inhoudelijk in op basis van de klantcontext. Geef de nieuwe JSON terug.'),
            ]],
        );

        $text = '';
        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        $decoded = $this->extractJson($text);

        if (! is_array($decoded)) {
            return ['ok' => false, 'error' => 'De AI gaf geen bruikbaar resultaat — probeer het nog eens.'];
        }

        // Structuur bewaken: ontbrekende sleutels vallen terug op huidig/standaard.
        foreach ($shape as $key => $default) {
            if (! array_key_exists($key, $decoded)) {
                $decoded[$key] = $data[$key] ?? $default;
            }
        }

        return ['ok' => true, 'data' => $decoded];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function systemPrompt(Quote $quote, array $meta, string $shapeJson): string
    {
        $quote->loadMissing(['customer', 'lead', 'lines']);

        $context = collect([
            'Klant: '.($quote->customer?->name ?? 'onbekend').($quote->customer?->city ? ' uit '.$quote->customer->city : ''),
            $quote->lead?->service ? 'Type werk: '.$quote->lead->service : null,
            $quote->lead?->description ? 'Omschrijving aanvraag: '.str($quote->lead->description)->limit(500) : null,
            'Offerte '.$quote->number.', totaal € '.number_format((float) $quote->total, 2, ',', '.').' incl. btw',
            $quote->lines->isNotEmpty()
                ? "Posten:\n".$quote->lines->map(fn ($line) => '- '.$line->description.' (€ '.number_format((float) $line->total, 2, ',', '.').($line->is_estimate ? ', stelpost' : '').')')->implode("\n")
                : null,
        ])->filter()->implode("\n");

        $label = $meta['label'] ?? 'blok';
        $description = $meta['description'] ?? '';

        return <<<PROMPT
            Je bent de offerte-tekstschrijver van Renovion, een renovatiebedrijf uit Arnhem (stucwerk, schilderwerk, tegelwerk, badkamerrenovaties, complete renovaties). Je werkt aan EEN blok van een offerte.

            Bloktype "{$label}": {$description}

            Dit blok heeft exact deze JSON-structuur (sleutels en types). Houd je hier strikt aan:
            {$shapeJson}

            Regels:
            - Geef ALLEEN een geldig JSON-object terug met exact dezelfde sleutels en structuur als hierboven. Geen extra tekst, geen markdown.
            - Schrijf professioneel maar toegankelijk Nederlands, in de toon van Renovion: "geen half werk en vage beloftes". U-vorm richting de klant.
            - Behoud arrays als arrays en objecten als objecten. Verzin geen nieuwe sleutels en laat geen sleutels weg.
            - Concreet en zonder overdreven verkooppraat.

            KLANTCONTEXT:
            {$context}
            PROMPT;
    }

    private function extractJson(string $text): ?array
    {
        $text = trim($text);

        // Strip eventuele ```json-omheining.
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $text) ?? $text;

        $decoded = json_decode(trim($text), true);

        if (is_array($decoded)) {
            return $decoded;
        }

        // Vangnet: pak het eerste {...}-object uit de tekst.
        if (preg_match('/\{.*\}/s', $text, $match)) {
            $decoded = json_decode($match[0], true);
        }

        return is_array($decoded) ? $decoded : null;
    }
}
