<?php

namespace App\Services;

use Anthropic\Client;
use App\Http\Requests\StoreCalculationRequest;
use App\Models\PriceItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

/**
 * AI-invoer voor de calculatiemodule (briefing §5): een omschrijving
 * (getypt of ingesproken) wordt vertaald naar calculatieregels op basis
 * van de kostendatabase. De regels worden altijd eerst aan de gebruiker
 * getoond vóór er iets wordt opgeslagen.
 */
class CalculationAssistant
{
    public function isConfigured(): bool
    {
        return filled(config('renovion.ai.api_key'));
    }

    /**
     * @param  list<string>  $sources
     * @return array{lines: list<array<string, mixed>>, note: string|null}|array{error: string}
     */
    public function proposeLines(string $description, array $sources): array
    {
        $items = PriceItem::active()
            ->when($sources !== [], fn ($query) => $query->whereIn('source', $sources))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $client = new Client(apiKey: (string) config('renovion.ai.api_key'));

        $response = $client->messages->create(
            model: (string) config('renovion.ai.model'),
            maxTokens: 3000,
            outputConfig: ['effort' => 'low'],
            system: [[
                'type' => 'text',
                'text' => $this->systemPrompt($items),
            ]],
            tools: [$this->tool()],
            messages: [['role' => 'user', 'content' => $description]],
        );

        foreach ($response->content as $block) {
            if ($block->type === 'tool_use' && $block->name === 'propose_calculation') {
                /** @var array{lines?: mixed, note?: mixed} $input */
                $input = json_decode(json_encode($block->input), true);

                $lines = collect(is_array($input['lines'] ?? null) ? $input['lines'] : [])
                    ->map(fn ($line) => Validator::make(
                        is_array($line) ? $line : [],
                        StoreCalculationRequest::lineRules(),
                    ))
                    ->filter(fn ($validator) => $validator->passes())
                    ->map(fn ($validator) => $validator->validated())
                    ->values()
                    ->all();

                if ($lines === []) {
                    return ['error' => 'Ik kon hier geen bruikbare calculatieregels uit halen. Beschrijf het werk iets concreter (bijv. oppervlaktes en welke werkzaamheden).'];
                }

                return [
                    'lines' => $lines,
                    'note' => is_string($input['note'] ?? null) ? $input['note'] : null,
                ];
            }
        }

        foreach ($response->content as $block) {
            if ($block->type === 'text' && filled($block->text)) {
                return ['error' => trim($block->text)];
            }
        }

        return ['error' => 'Ik kon geen voorstel maken. Probeer het opnieuw met een concretere omschrijving.'];
    }

    /**
     * @param  Collection<int, PriceItem>  $items
     */
    private function systemPrompt($items): string
    {
        $library = $items
            ->map(fn (PriceItem $item) => implode(' | ', [
                $item->id,
                $item->type->value,
                $item->name,
                $item->unit,
                number_format($item->effectivePrice(), 2, '.', ''),
                'opslag '.(float) $item->surcharge_pct.'%',
                $item->source.' ('.$item->edition.')',
            ]))
            ->implode("\n");

        return <<<PROMPT
            Je bent de calculatie-assistent van renovatiebedrijf Renovion (Arnhem: stucwerk, schilderwerk, tegelwerk, badkamerrenovaties, complete renovaties).
            Vertaal de omschrijving van de gebruiker naar concrete calculatieregels via de tool propose_calculation. Roep altijd de tool aan, behalve als de omschrijving echt geen bouwkundige inhoud heeft.

            Regels:
            - Gebruik waar mogelijk regels uit de PRIJSBIBLIOTHEEK hieronder: neem dan price_item_id, eenheid en prijs van dat item over (prijs mag je aanpassen als de omschrijving daar aanleiding toe geeft).
            - Staat iets niet in de bibliotheek, maak dan een vrije regel zonder price_item_id met een realistische Nederlandse marktprijs (2026).
            - Schat hoeveelheden op basis van de omschrijving (oppervlaktes, aantallen). Noem elke aanname kort in "note".
            - Gebruik type "stelpost" voor onderdelen die de klant nog moet kiezen of die onzeker zijn (bijv. sanitair, tegels boven standaard).
            - Denk aan bijkomende posten die vakmensen vaak vergeten: sloop/afvoer, afvalcontainer, bouwplaatsvoorzieningen, kitwerk, elektra-aanpassingen.
            - Alle prijzen exclusief btw. Omschrijvingen kort en professioneel, in het Nederlands.
            - Risico/onvoorzien, marge en btw worden buiten de regels om berekend — neem die NIET als regel op.

            PRIJSBIBLIOTHEEK (id | type | naam | eenheid | prijs | opslag | bron):
            {$library}
            PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function tool(): array
    {
        return [
            'name' => 'propose_calculation',
            'description' => 'Stel calculatieregels voor op basis van de projectomschrijving.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'lines' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'price_item_id' => ['type' => 'integer', 'description' => 'Id uit de prijsbibliotheek; weglaten voor een vrije regel'],
                                'type' => ['type' => 'string', 'enum' => ['arbeid', 'materiaal', 'onderaannemer', 'materieel', 'stelpost']],
                                'description' => ['type' => 'string', 'description' => 'Korte omschrijving van de regel'],
                                'quantity' => ['type' => 'number', 'description' => 'Hoeveelheid'],
                                'unit' => ['type' => 'string', 'description' => 'Eenheid: m2, m1, uur, stuk of post'],
                                'unit_price' => ['type' => 'number', 'description' => 'Prijs per eenheid, exclusief btw'],
                                'surcharge_pct' => ['type' => 'number', 'description' => 'Opslagpercentage op deze regel, meestal 0'],
                            ],
                            'required' => ['type', 'description', 'quantity', 'unit_price'],
                        ],
                    ],
                    'note' => ['type' => 'string', 'description' => 'Korte toelichting met aannames, in het Nederlands'],
                ],
                'required' => ['lines'],
            ],
        ];
    }
}
