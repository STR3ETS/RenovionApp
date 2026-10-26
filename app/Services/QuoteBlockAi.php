<?php

namespace App\Services;

use Anthropic\Client;
use App\Models\Quote;
use App\Support\QuoteBlockRegistry;
use App\Support\QuoteText;

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
            return ['ok' => false, 'error' => 'De AI gaf geen bruikbaar resultaat - probeer het nog eens.'];
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
     * Nova bouwt de hele offerte: een complete blokkenlijst op basis van de
     * klant, de aanvraag en de calculatieposten. Vastgezette blokken
     * (data._keep) blijven mét inhoud op hun plek staan; de kop blijft altijd.
     *
     * @param  list<array{id: string, type: string, data: array<string, mixed>}>  $current
     * @return array{ok: bool, blocks?: list<array{id: string, type: string, data: array<string, mixed>}>, error?: string}
     */
    public function generate(Quote $quote, array $current, string $instruction = ''): array
    {
        $registry = QuoteBlockRegistry::registry();
        $jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        // De kop blijft altijd staan; vastgezette blokken onthouden we met hun positie.
        $hero = collect($current)->firstWhere('type', 'hero') ?? QuoteBlockRegistry::make('hero');
        $pinned = collect($current)
            ->values()
            ->filter(fn (array $block) => ($block['data']['_keep'] ?? false) && $block['type'] !== 'hero')
            ->all();

        $catalog = collect($registry)
            ->except(['hero', 'image', 'divider'])
            ->map(fn (array $meta, string $type) => [
                'type' => $type,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'data' => $meta['default'],
            ])
            ->values()
            ->all();

        $pinnedNote = $pinned === []
            ? ''
            : "\nDeze blokken staan al vast en blijven staan - genereer ze NIET opnieuw en herhaal hun inhoud niet: "
                .collect($pinned)->map(fn (array $block) => $block['type'].' ("'.QuoteText::plain($block['data']['title'] ?? '').'")')->implode(', ').'.';

        $system = <<<PROMPT
            Je bent de offerte-schrijver van Renovion, een renovatiebedrijf uit Arnhem (stucwerk, schilderwerk, tegelwerk, badkamerrenovaties, complete renovaties). Je stelt een complete offerte samen uit blokken.

            Beschikbare bloktypes, elk met hun exacte JSON-datastructuur:
            {$this->encode($catalog, $jsonFlags)}

            Regels:
            - Geef ALLEEN een geldige JSON-array terug: [{"type": "...", "data": {...}}, ...]. Geen extra tekst, geen markdown.
            - Kies 5 tot 8 blokken die bij deze klant en dit werk passen, in een logische volgorde (samenvatting eerst, investering en voorwaarden richting het einde).
            - Neem het blok "investment" precies één keer op; de posten en bedragen komen uit het systeem, jij schrijft alleen title/intro/note.
            - Houd per blok exact de sleutels en structuur van het bloktype aan. Geen nieuwe sleutels.
            - Schrijf professioneel maar toegankelijk Nederlands, in de toon van Renovion: "geen half werk en vage beloftes". U-vorm richting de klant. Concreet, zonder overdreven verkooppraat.
            - Gebruik nooit een em-dash of het teken · in teksten; schrijf gewone zinnen met komma's en punten.{$pinnedNote}

            KLANTCONTEXT:
            {$this->context($quote)}
            PROMPT;

        $client = new Client(apiKey: (string) config('renovion.ai.api_key'));

        $response = $client->messages->create(
            model: (string) config('renovion.ai.model'),
            maxTokens: 6000,
            outputConfig: ['effort' => 'low'],
            system: [['type' => 'text', 'text' => $system]],
            messages: [[
                'role' => 'user',
                'content' => $instruction !== ''
                    ? "Bouw de offerte. Wensen van de medewerker: {$instruction}"
                    : 'Bouw de offerte op basis van de klantcontext.',
            ]],
        );

        $text = '';
        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        $decoded = $this->extractJson($text);
        $items = is_array($decoded) ? (array_is_list($decoded) ? $decoded : [$decoded]) : null;

        if ($items === null || $items === []) {
            return ['ok' => false, 'error' => 'Nova gaf geen bruikbaar resultaat - probeer het nog eens.'];
        }

        // Alleen bekende types, structuur per blok bewaken, singletons één keer.
        $pinnedTypes = array_column($pinned, 'type');
        $seen = [];
        $generated = [];

        foreach ($items as $item) {
            $type = is_array($item) ? ($item['type'] ?? null) : null;
            $meta = $registry[$type] ?? null;

            if ($meta === null || $type === 'hero') {
                continue;
            }

            if (($meta['singleton'] ?? false) && (in_array($type, $seen, true) || in_array($type, $pinnedTypes, true))) {
                continue;
            }

            $shape = $meta['default'] ?? [];
            $data = is_array($item['data'] ?? null) ? array_intersect_key($item['data'], $shape) : [];

            $seen[] = $type;
            $generated[] = QuoteBlockRegistry::make($type, array_merge($shape, $data));
        }

        if ($generated === []) {
            return ['ok' => false, 'error' => 'Nova gaf geen bruikbare blokken terug - probeer het nog eens.'];
        }

        if (! in_array('investment', $seen, true) && ! in_array('investment', $pinnedTypes, true)) {
            $generated[] = QuoteBlockRegistry::make('investment');
        }

        // Kop vooraan, vastgezette blokken terug op (ongeveer) hun oude plek.
        $blocks = [$hero, ...$generated];

        foreach ($pinned as $block) {
            $index = collect($current)->search(fn (array $b) => ($b['id'] ?? null) === ($block['id'] ?? null));
            array_splice($blocks, min((int) $index, count($blocks)), 0, [$block]);
        }

        return ['ok' => true, 'blocks' => array_values($blocks)];
    }

    private function encode(mixed $value, int $flags): string
    {
        return (string) json_encode($value, $flags);
    }

    private function context(Quote $quote): string
    {
        $quote->loadMissing(['customer', 'lead', 'lines']);

        return collect([
            'Klant: '.($quote->customer?->name ?? 'onbekend').($quote->customer?->city ? ' uit '.$quote->customer->city : ''),
            $quote->lead?->service ? 'Type werk: '.$quote->lead->service : null,
            $quote->lead?->description ? 'Omschrijving aanvraag: '.str($quote->lead->description)->limit(500) : null,
            'Offerte '.$quote->number.', totaal € '.number_format((float) $quote->total, 2, ',', '.').' incl. btw',
            $quote->lines->isNotEmpty()
                ? "Posten:\n".$quote->lines->map(fn ($line) => '- '.$line->description.' (€ '.number_format((float) $line->total, 2, ',', '.').($line->is_estimate ? ', stelpost' : '').')')->implode("\n")
                : null,
        ])->filter()->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function systemPrompt(Quote $quote, array $meta, string $shapeJson): string
    {
        $context = $this->context($quote);
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
            - Gebruik nooit een em-dash of het teken · in teksten; schrijf gewone zinnen met komma's en punten.

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

        // Vangnet: pak de eerste [...]-array of het eerste {...}-object uit de tekst.
        if (preg_match('/\[.*\]/s', $text, $match) || preg_match('/\{.*\}/s', $text, $match)) {
            $decoded = json_decode($match[0], true);
        }

        return is_array($decoded) ? $decoded : null;
    }
}
