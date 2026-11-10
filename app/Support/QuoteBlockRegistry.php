<?php

namespace App\Support;

use App\Models\Quote;
use App\Models\QuoteTemplate;
use Illuminate\Support\Str;

/**
 * Blokkenregister voor de offerte-builder (briefing §6, EasyDash-concept):
 * getypeerde blokken met eigen datastructuur, inline bewerkbaar in het
 * live document. Overgenomen uit de Eazyonline-offertetool en toegesneden
 * op renovatie-offertes.
 */
class QuoteBlockRegistry
{
    /**
     * Registry: type => [label, icon (x-icon naam), description, locked?,
     * singleton?, default data].
     *
     * @return array<string, array<string, mixed>>
     */
    public static function registry(): array
    {
        return [
            'hero' => [
                'label' => 'Kop',
                'icon' => 'building-office',
                'description' => 'Titel, klant en offertenummer',
                'locked' => true,
                'singleton' => true,
                // Achtergrond: kleur (hex), optionele foto met kleur-overlay (0-95%) en tekstkleur (auto/light/dark), zie QuoteHeroStyle.
                'default' => [
                    'title' => 'Offerte',
                    'subtitle' => 'Renovatiewerkzaamheden',
                    'bg' => QuoteHeroStyle::DEFAULT_BG,
                    'image' => '',
                    'overlay' => QuoteHeroStyle::DEFAULT_OVERLAY,
                    'text' => 'auto',
                ],
            ],
            'summary' => [
                'label' => 'Samenvatting',
                'icon' => 'document-text',
                'description' => 'Korte intro met highlights',
                'default' => [
                    'title' => 'Samenvatting van het voorstel',
                    'text' => 'Een zorgeloze renovatie, vakkundig uitgevoerd en strak opgeleverd.',
                    'highlights' => [
                        'Eén aanspreekpunt van start tot oplevering',
                        'Vaste prijs, geen verrassingen achteraf',
                    ],
                ],
            ],
            'text' => [
                'label' => 'Tekst',
                'icon' => 'pencil-square',
                'description' => 'Titel met lopende tekst',
                'default' => [
                    'title' => 'Titel',
                    'body' => 'Schrijf hier de tekst van dit blok.',
                ],
            ],
            'list' => [
                'label' => 'Lijst',
                'icon' => 'clipboard-check',
                'description' => 'Opsomming met vinkjes',
                'default' => [
                    'title' => 'Werkzaamheden',
                    'intro' => '',
                    'items' => ['Eerste punt', 'Tweede punt'],
                ],
            ],
            'deliverables' => [
                'label' => 'Wat u krijgt',
                'icon' => 'check',
                'description' => 'Leveringen, alles inbegrepen',
                'default' => [
                    'title' => 'Wat u van ons krijgt',
                    'intro' => 'Dit is allemaal inbegrepen in deze offerte.',
                    'items' => [
                        ['text' => 'Vakkundige uitvoering door ons eigen team', 'included' => true],
                        ['text' => 'Afdekken en beschermen van de woning', 'included' => true],
                        ['text' => 'Dagelijks opgeruimde werkplek en afvoer van afval', 'included' => true],
                    ],
                ],
            ],
            'analysis' => [
                'label' => 'Bevindingen',
                'icon' => 'magnifying-glass',
                'description' => 'Wat goed is en wat aandacht vraagt',
                'default' => [
                    'title' => 'Bevindingen uit de opname',
                    'good' => ['De constructie is in goede staat', 'Leidingwerk recent vernieuwd'],
                    'attention' => ['Ondergrond moet eerst egaal worden gemaakt', 'Kitranden zijn aan vervanging toe'],
                ],
            ],
            'goals' => [
                'label' => 'Wensen & doelen',
                'icon' => 'bolt',
                'description' => 'Waar het resultaat aan moet voldoen',
                'default' => [
                    'title' => 'Uw wensen & doelen',
                    'intro' => 'Dit resultaat spraken we samen af.',
                    'items' => ['Een frisse, strakke uitstraling', 'Onderhoudsarm en duurzaam afgewerkt'],
                ],
            ],
            'rooms' => [
                'label' => 'Ruimtes',
                'icon' => 'home',
                'description' => 'Werkzaamheden per ruimte',
                'default' => [
                    'title' => 'Werkzaamheden per ruimte',
                    'intro' => '',
                    'items' => [
                        ['name' => 'Woonkamer', 'tasks' => ['Wanden stucen', 'Plafond sauzen']],
                        ['name' => 'Badkamer', 'tasks' => ['Tegelwerk vloer en wanden', 'Sanitair plaatsen']],
                    ],
                ],
            ],
            'phases' => [
                'label' => 'Fasering',
                'icon' => 'calendar',
                'description' => 'Stappen van het werk',
                'default' => [
                    'title' => 'Aanpak & planning',
                    'intro' => 'Zo verloopt het werk van start tot oplevering.',
                    'items' => [
                        ['title' => 'Voorbereiding', 'body' => 'Opname, werkvoorbereiding en inkoop.'],
                        ['title' => 'Uitvoering', 'body' => 'Het werk wordt uitgevoerd volgens planning.'],
                        ['title' => 'Oplevering', 'body' => 'Controle, oplevering en nazorg.'],
                    ],
                ],
            ],
            'investment' => [
                'label' => 'Investering',
                'icon' => 'currency-euro',
                'description' => 'Posten en totalen uit de offerte',
                'singleton' => true,
                'default' => [
                    'title' => 'Investering',
                    'intro' => 'Onderstaande investering is opgebouwd uit heldere posten, inclusief arbeid, materiaal en coördinatie.',
                    'note' => 'Stelposten worden achteraf verrekend op basis van de werkelijke keuze en aankoopprijs.',
                ],
            ],
            'support' => [
                'label' => 'Garantie & nazorg',
                'icon' => 'wrench-screwdriver',
                'description' => 'Wat u krijgt na oplevering',
                'default' => [
                    'title' => 'Garantie & nazorg',
                    'intro' => 'Na de oplevering laten we u niet los.',
                    'items' => [
                        ['text' => 'Garantie op al het uitgevoerde werk', 'included' => true],
                        ['text' => 'Opleverrapport met foto-bewijs', 'included' => true],
                        ['text' => 'Eén telefoontje en we staan weer voor u klaar', 'included' => true],
                    ],
                ],
            ],
            'terms' => [
                'label' => 'Randvoorwaarden',
                'icon' => 'folder',
                'description' => 'Voorwaarden en scope',
                'default' => [
                    'title' => 'Randvoorwaarden & scope',
                    'text' => 'Deze offerte is gebaseerd op de besproken wensen en de opname ter plaatse. Werkzaamheden buiten de omschreven scope worden vooraf als meerwerk afgestemd.',
                ],
            ],
            'faq' => [
                'label' => 'Veelgestelde vragen',
                'icon' => 'chat-bubble',
                'description' => 'Vraag en antwoord',
                'default' => [
                    'title' => 'Veelgestelde vragen',
                    'items' => [
                        ['q' => 'Hoe lang duurt het werk?', 'a' => 'De doorlooptijd stemmen we na akkoord met u af in de planning.'],
                    ],
                ],
            ],
            'social_proof' => [
                'label' => 'Ervaringen',
                'icon' => 'users',
                'description' => 'Wat klanten over ons zeggen',
                'default' => [
                    'title' => 'Wat onze klanten zeggen',
                    'items' => [
                        ['name' => 'Familie de Boer', 'context' => 'Badkamerrenovatie, Arnhem', 'quote' => 'Strak opgeleverd, netjes gewerkt en altijd bereikbaar. Precies wat ze beloofden.'],
                    ],
                ],
            ],
            'image' => [
                'label' => 'Foto',
                'icon' => 'camera',
                'description' => 'Afbeelding met bijschrift',
                'default' => [
                    'url' => '',
                    'caption' => '',
                ],
            ],
            'divider' => [
                'label' => 'Scheiding',
                'icon' => 'x-mark',
                'description' => 'Rustpunt tussen blokken',
                'default' => [],
            ],
        ];
    }

    public static function newId(): string
    {
        return 'b_'.Str::random(12);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{id: string, type: string, data: array<string, mixed>}
     */
    public static function make(string $type, array $overrides = []): array
    {
        $default = self::registry()[$type]['default'] ?? [];

        return [
            'id' => self::newId(),
            'type' => $type,
            'data' => array_merge($default, $overrides),
        ];
    }

    /**
     * Is dit al het nieuwe (getypeerde) formaat?
     *
     * @param  array<int, mixed>|null  $blocks
     */
    public static function isTyped(?array $blocks): bool
    {
        $first = $blocks[0] ?? null;

        return is_array($first) && isset($first['type'], $first['data']);
    }

    /**
     * Zorg dat de blokken in het getypeerde formaat staan; converteert het
     * oude formaat ({key, title, body, enabled}) on-the-fly.
     *
     * @param  array<int, mixed>|null  $blocks
     * @return list<array{id: string, type: string, data: array<string, mixed>}>
     */
    public static function ensureTyped(?array $blocks, ?Quote $quote = null): array
    {
        if ($blocks === null || $blocks === []) {
            return [
                self::make('hero', ['subtitle' => $quote?->lead?->service ?? 'Renovatiewerkzaamheden']),
                self::make('investment'),
            ];
        }

        if (self::isTyped($blocks)) {
            return array_values($blocks);
        }

        $typed = [self::make('hero', ['subtitle' => $quote?->lead?->service ?? 'Renovatiewerkzaamheden'])];

        foreach ($blocks as $block) {
            if (! is_array($block) || ! ($block['enabled'] ?? true)) {
                continue;
            }

            $typed[] = self::fromLegacy($block);
        }

        return $typed;
    }

    /**
     * Eén legacy-blok (key/title/body) omzetten naar een getypeerd blok.
     *
     * @param  array<string, mixed>  $block
     * @return array{id: string, type: string, data: array<string, mixed>}
     */
    private static function fromLegacy(array $block): array
    {
        $key = $block['key'] ?? '';
        $title = $block['title'] ?? 'Tekst';
        $body = trim((string) ($block['body'] ?? ''));

        // Regels die met "- " beginnen worden lijstitems.
        $lines = array_values(array_filter(array_map('trim', explode("\n", $body))));
        $isList = $lines !== [] && collect($lines)->every(fn (string $line) => str_starts_with($line, '- '));

        if ($key === 'investering') {
            return self::make('investment', ['title' => $title, 'intro' => $body]);
        }

        if ($key === 'stelposten') {
            return self::make('text', ['title' => $title, 'body' => $body]);
        }

        if ($key === 'faq') {
            $items = collect($lines)
                ->map(function (string $line) {
                    $pos = mb_strpos($line, '? ');

                    return $pos === false
                        ? ['q' => $line, 'a' => '']
                        : ['q' => mb_substr($line, 0, $pos + 1), 'a' => trim(mb_substr($line, $pos + 2))];
                })
                ->values()
                ->all();

            return self::make('faq', $items === [] ? ['title' => $title] : ['title' => $title, 'items' => $items]);
        }

        if ($isList) {
            return self::make('list', [
                'title' => $title,
                'intro' => '',
                'items' => array_map(fn (string $line) => mb_substr($line, 2), $lines),
            ]);
        }

        return self::make('text', ['title' => $title, 'body' => $body]);
    }

    /**
     * Bouw getypeerde blokken uit de tekst-templates per werktype
     * ([QuoteTemplates]) - gebruikt door de seeder en als vangnet.
     *
     * @return list<array{id: string, type: string, data: array<string, mixed>}>
     */
    public static function fromTexts(string $template, ?Quote $quote = null): array
    {
        $legacy = QuoteTemplates::blocks($template, $quote);

        $typed = [self::make('hero', [
            'title' => 'Offerte',
            'subtitle' => QuoteTemplates::options()[$template] ?? 'Renovatiewerkzaamheden',
        ])];

        foreach ($legacy as $block) {
            $typed[] = self::fromLegacy($block);
        }

        return $typed;
    }

    /**
     * Startblokken voor een nieuwe offerte: het DB-template dat bij het type
     * werk past, met de klantgegevens ingevuld.
     *
     * @return list<array{id: string, type: string, data: array<string, mixed>}>
     */
    public static function forNewQuote(Quote $quote): array
    {
        $slug = QuoteTemplates::guess($quote->lead?->service);

        $template = QuoteTemplate::firstWhere('slug', $slug)
            ?? QuoteTemplate::firstWhere('slug', 'renovatie');

        if ($template !== null) {
            return self::substitute(array_map(fn (array $block) => [
                'id' => self::newId(),
                'type' => $block['type'],
                'data' => $block['data'] ?? [],
            ], $template->blocks), $quote);
        }

        return self::fromTexts($slug, $quote);
    }

    /**
     * Vervang {klant}/{plaats}-tokens in alle tekstvelden - gebruikt bij het
     * toepassen van een template op een concrete offerte.
     *
     * @param  list<array{id: string, type: string, data: array<string, mixed>}>  $blocks
     * @return list<array{id: string, type: string, data: array<string, mixed>}>
     */
    public static function substitute(array $blocks, Quote $quote): array
    {
        $replacements = [
            '{klant}' => $quote->customer?->name ?? 'de opdrachtgever',
            '{plaats}' => $quote->customer?->city ?? 'de projectlocatie',
        ];

        $walk = function ($value) use (&$walk, $replacements) {
            if (is_string($value)) {
                return strtr($value, $replacements);
            }

            if (is_array($value)) {
                return array_map($walk, $value);
            }

            return $value;
        };

        return array_map(fn (array $block) => [...$block, 'data' => $walk($block['data'])], $blocks);
    }

    /**
     * Schoon een uit de browser afkomstige blokkenlijst op: alleen bekende
     * types, altijd een id, data als array en strings getrimd op lengte.
     *
     * @param  array<int, mixed>  $blocks
     * @return list<array{id: string, type: string, data: array<string, mixed>}>
     */
    public static function normalize(array $blocks): array
    {
        $registry = self::registry();
        $clean = [];

        foreach ($blocks as $block) {
            if (! is_array($block) || ! isset($registry[$block['type'] ?? ''])) {
                continue;
            }

            $clean[] = [
                'id' => is_string($block['id'] ?? null) && $block['id'] !== '' ? mb_substr($block['id'], 0, 40) : self::newId(),
                'type' => $block['type'],
                'data' => self::cleanData(is_array($block['data'] ?? null) ? $block['data'] : []),
            ];
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function cleanData(array $data, int $depth = 0): array
    {
        if ($depth > 3) {
            return [];
        }

        $clean = [];

        foreach ($data as $key => $value) {
            // "_keep" (vastgezet bij Nova) is de enige toegestane interne sleutel.
            if (! is_string($key) && ! is_int($key)) {
                continue;
            }

            if (is_string($key) && str_starts_with($key, '_')) {
                if ($key === '_keep' && $value) {
                    $clean['_keep'] = true;
                }

                continue;
            }

            $clean[$key] = match (true) {
                is_string($value) => QuoteText::clean(mb_substr($value, 0, 10000)),
                is_array($value) => self::cleanData($value, $depth + 1),
                is_bool($value), is_int($value), is_float($value) => $value,
                default => null,
            };
        }

        return $clean;
    }
}
