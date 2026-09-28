<?php

namespace Database\Seeders;

use App\Models\PriceItem;
use Illuminate\Database\Seeder;

/**
 * Startset "Renovion praktijkprijzen" voor de kostendatabase (briefing §5).
 * Idempotent: bestaande items (bron + editie + naam) worden niet overschreven,
 * zodat handmatig bijgestelde prijzen blijven staan. Archidat-bronnen kunnen
 * later worden geïmporteerd zodra de licentie dat toestaat.
 */
class PriceLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // Arbeid (uurtarieven)
            ['name' => 'Timmerman (uurtarief)', 'type' => 'arbeid', 'unit' => 'uur', 'unit_price' => 62.50],
            ['name' => 'Stukadoor (uurtarief)', 'type' => 'arbeid', 'unit' => 'uur', 'unit_price' => 60.00],
            ['name' => 'Tegelzetter (uurtarief)', 'type' => 'arbeid', 'unit' => 'uur', 'unit_price' => 62.50],
            ['name' => 'Schilder (uurtarief)', 'type' => 'arbeid', 'unit' => 'uur', 'unit_price' => 57.50],
            ['name' => 'Allround klusser / sloopwerk (uurtarief)', 'type' => 'arbeid', 'unit' => 'uur', 'unit_price' => 52.50],

            // Onderaannemers
            ['name' => 'Loodgieter / installateur (uurtarief)', 'type' => 'onderaannemer', 'unit' => 'uur', 'unit_price' => 72.50],
            ['name' => 'Elektricien (uurtarief)', 'type' => 'onderaannemer', 'unit' => 'uur', 'unit_price' => 70.00],

            // Stucwerk
            ['name' => 'Wanden glad stucwerk (incl. materiaal)', 'type' => 'arbeid', 'unit' => 'm2', 'unit_price' => 27.50],
            ['name' => 'Plafond glad stucwerk (incl. materiaal)', 'type' => 'arbeid', 'unit' => 'm2', 'unit_price' => 32.50],
            ['name' => 'Behangklaar stucwerk', 'type' => 'arbeid', 'unit' => 'm2', 'unit_price' => 21.50],
            ['name' => 'Spachtelputz aanbrengen', 'type' => 'arbeid', 'unit' => 'm2', 'unit_price' => 24.00],

            // Schilderwerk
            ['name' => 'Binnenschilderwerk wanden/plafonds (2x dekkend)', 'type' => 'arbeid', 'unit' => 'm2', 'unit_price' => 14.50],
            ['name' => 'Kozijnen/deuren aflakken (binnen)', 'type' => 'arbeid', 'unit' => 'm1', 'unit_price' => 17.50],
            ['name' => 'Buitenschilderwerk kozijnen', 'type' => 'arbeid', 'unit' => 'm1', 'unit_price' => 24.50],

            // Tegelwerk
            ['name' => 'Vloertegels leggen t/m 60x60', 'type' => 'arbeid', 'unit' => 'm2', 'unit_price' => 47.50],
            ['name' => 'Wandtegels zetten t/m 30x60', 'type' => 'arbeid', 'unit' => 'm2', 'unit_price' => 52.50],
            ['name' => 'Vloertegels (materiaal, middenklasse)', 'type' => 'materiaal', 'unit' => 'm2', 'unit_price' => 32.50],
            ['name' => 'Wandtegels (materiaal, middenklasse)', 'type' => 'materiaal', 'unit' => 'm2', 'unit_price' => 29.50],
            ['name' => 'Kitwerk sanitair', 'type' => 'arbeid', 'unit' => 'm1', 'unit_price' => 9.50],

            // Badkamer
            ['name' => 'Badkamer strippen en afvoeren', 'type' => 'arbeid', 'unit' => 'post', 'unit_price' => 1450.00],
            ['name' => 'Leidingwerk badkamer verleggen (water/afvoer)', 'type' => 'onderaannemer', 'unit' => 'post', 'unit_price' => 1850.00],
            ['name' => 'Inloopdouche compleet plaatsen (excl. sanitair)', 'type' => 'arbeid', 'unit' => 'post', 'unit_price' => 1250.00],
            ['name' => 'Wandcloset plaatsen (incl. inbouwreservoir)', 'type' => 'arbeid', 'unit' => 'stuk', 'unit_price' => 675.00],
            ['name' => 'Badkamermeubel monteren en aansluiten', 'type' => 'arbeid', 'unit' => 'stuk', 'unit_price' => 425.00],
            ['name' => 'Sanitair (stelpost middenklasse badkamer)', 'type' => 'stelpost', 'unit' => 'post', 'unit_price' => 3500.00],

            // Elektra
            ['name' => 'Wandcontactdoos/schakelpunt bijplaatsen', 'type' => 'onderaannemer', 'unit' => 'stuk', 'unit_price' => 145.00],
            ['name' => 'Groepenkast uitbreiden/vervangen', 'type' => 'onderaannemer', 'unit' => 'post', 'unit_price' => 1250.00],
            ['name' => 'Elektra badkamer (aarding, spots, ventilatie)', 'type' => 'onderaannemer', 'unit' => 'post', 'unit_price' => 1450.00],

            // Vloeren & wanden
            ['name' => 'Cementdekvloer aanbrengen', 'type' => 'onderaannemer', 'unit' => 'm2', 'unit_price' => 27.50],
            ['name' => 'Vloerverwarming infrezen', 'type' => 'onderaannemer', 'unit' => 'm2', 'unit_price' => 34.50],
            ['name' => 'Metal-stud wand plaatsen (incl. beplating)', 'type' => 'arbeid', 'unit' => 'm2', 'unit_price' => 72.50],
            ['name' => 'Plafond verlagen (gipsplaat op rachels)', 'type' => 'arbeid', 'unit' => 'm2', 'unit_price' => 57.50],

            // Overig
            ['name' => 'Afvalcontainer 10m3 (huur + storten)', 'type' => 'materieel', 'unit' => 'stuk', 'unit_price' => 475.00],
            ['name' => 'Steiger (huur per week)', 'type' => 'materieel', 'unit' => 'stuk', 'unit_price' => 285.00],
            ['name' => 'Bouwplaatsvoorzieningen en bescherming', 'type' => 'materiaal', 'unit' => 'post', 'unit_price' => 350.00],
            ['name' => 'Onvoorzien constructief (stelpost)', 'type' => 'stelpost', 'unit' => 'post', 'unit_price' => 1500.00],
        ];

        foreach ($items as $item) {
            PriceItem::firstOrCreate(
                [
                    'source' => 'Renovion praktijkprijzen',
                    'edition' => '2026',
                    'name' => $item['name'],
                ],
                [
                    'type' => $item['type'],
                    'unit' => $item['unit'],
                    'unit_price' => $item['unit_price'],
                    'surcharge_pct' => 0,
                    'index_factor' => 1,
                    'active' => true,
                    'last_checked_at' => '2026-09-01',
                ],
            );
        }
    }
}
