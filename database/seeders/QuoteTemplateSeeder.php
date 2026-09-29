<?php

namespace Database\Seeders;

use App\Models\QuoteTemplate;
use App\Support\QuoteBlockRegistry;
use App\Support\QuoteTemplates;
use Illuminate\Database\Seeder;

/**
 * Standaard offerte-templates voor de builder (briefing §6): blanco plus de
 * voorgeconditioneerde Renovion-offertes per type werk. Idempotent op slug;
 * eigen (niet-default) templates blijven ongemoeid.
 */
class QuoteTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $stripIds = fn (array $blocks) => array_map(
            fn (array $block) => ['type' => $block['type'], 'data' => $block['data']],
            $blocks,
        );

        QuoteTemplate::updateOrCreate(
            ['slug' => 'blanco'],
            [
                'name' => 'Blanco',
                'description' => 'Lege offerte: alleen de kop en de investering.',
                'blocks' => $stripIds([
                    QuoteBlockRegistry::make('hero'),
                    QuoteBlockRegistry::make('investment'),
                ]),
                'is_default' => true,
                'sort_order' => 0,
            ],
        );

        foreach (QuoteTemplates::options() as $slug => $label) {
            QuoteTemplate::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $label,
                    'description' => 'Voorgeconditioneerde Renovion-offerte voor '.mb_strtolower($label).'.',
                    'blocks' => $stripIds(QuoteBlockRegistry::fromTexts($slug)),
                    'is_default' => true,
                    'sort_order' => 10,
                ],
            );
        }
    }
}
