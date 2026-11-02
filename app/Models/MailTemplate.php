<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een mailtemplate: een onderwerp en een reeks blokken met variabelen erin.
 *
 * Variabelen staan tussen dubbele accolades. Wat niet gevuld kan worden blijft
 * zichtbaar als ontbrekend in plaats van stil leeg te blijven: een mail met
 * "beste {{klant_naam}}" erin wil je zien voordat hij de deur uit gaat.
 */
#[Fillable(['key', 'name', 'subject', 'moment', 'blocks', 'active', 'updated_by'])]
class MailTemplate extends Model
{
    /** @var array<string, array{label: string, hint: string}> */
    public const BLOCK_TYPES = [
        'kop' => ['label' => 'Kop', 'hint' => 'Een regel die eruit springt'],
        'tekst' => ['label' => 'Tekst', 'hint' => 'Een alinea'],
        'lijst' => ['label' => 'Opsomming', 'hint' => 'Een regel per punt'],
        'knop' => ['label' => 'Knop', 'hint' => 'Tekst en link, gescheiden door een pijp'],
        'gegevens' => ['label' => 'Gegevens', 'hint' => 'Label en waarde per regel, gescheiden door een dubbele punt'],
        'afsluiting' => ['label' => 'Afsluiting', 'hint' => 'Groet en ondertekening'],
    ];

    /**
     * De variabelen die het systeem kan vullen.
     *
     * @var array<string, string>
     */
    public const VARIABLES = [
        'klant_naam' => 'Naam van de klant',
        'contactpersoon' => 'Contactpersoon bij de klant',
        'project_naam' => 'Naam van het project',
        'project_adres' => 'Adres van het project',
        'offertenummer' => 'Nummer van de offerte',
        'offertebedrag' => 'Bedrag van de offerte',
        'offertelink' => 'Link naar de offerte voor de klant',
        'aanneemsom' => 'Aanneemsom inclusief meerwerk',
        'startdatum' => 'Startdatum van het werk',
        'opleverdatum' => 'Verwachte opleverdatum',
        'projectleider' => 'Naam van de projectleider',
        'factuurnummer' => 'Nummer van de factuur',
        'factuurbedrag' => 'Bedrag van de factuur',
        'vervaldatum' => 'Vervaldatum van de factuur',
        'bedrijf_naam' => 'Naam van ons bedrijf',
        'bedrijf_telefoon' => 'Ons telefoonnummer',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'active' => 'boolean',
        ];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * De variabelen die in dit template gebruikt worden, in onderwerp en blokken.
     *
     * @return list<string>
     */
    public function usedVariables(): array
    {
        $haystack = $this->subject.' '.collect($this->blocks ?? [])->pluck('body')->join(' ');

        preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $haystack, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * Variabelen die dit template gebruikt maar die het systeem niet kent. Een
     * typefout in een variabele valt zo op voordat de mail de deur uit gaat.
     *
     * @return list<string>
     */
    public function unknownVariables(): array
    {
        return array_values(array_diff($this->usedVariables(), array_keys(self::VARIABLES)));
    }

    /**
     * Het template vullen met echte waarden. Wat niet gevuld kan worden blijft
     * als [ontbreekt: naam] staan, zodat het opvalt.
     *
     * @param  array<string, string|null>  $values
     */
    public function render(array $values): string
    {
        $out = [];

        foreach ($this->blocks ?? [] as $block) {
            $body = $this->substitute($block['body'] ?? '', $values);

            $out[] = match ($block['type'] ?? 'tekst') {
                'kop' => mb_strtoupper($body),
                'lijst' => collect(preg_split('/\r?\n/', $body))
                    ->filter()
                    ->map(fn (string $line) => '- '.trim($line))
                    ->join("\n"),
                'knop' => str_contains($body, '|')
                    ? trim(str($body)->before('|')->value()).': '.trim(str($body)->after('|')->value())
                    : $body,
                default => $body,
            };
        }

        return implode("\n\n", array_filter($out));
    }

    public function renderSubject(array $values): string
    {
        return $this->substitute($this->subject, $values);
    }

    /**
     * @param  array<string, string|null>  $values
     */
    private function substitute(string $text, array $values): string
    {
        return preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            fn (array $m) => filled($values[$m[1]] ?? null)
                ? (string) $values[$m[1]]
                : '[ontbreekt: '.$m[1].']',
            $text,
        ) ?? $text;
    }

    /**
     * De templates waarmee Renovion begint: de zeven momenten waarop er een mail
     * naar een klant gaat.
     *
     * @return list<array<string, mixed>>
     */
    public static function defaults(): array
    {
        return [
            [
                'key' => 'aanvraag-ontvangen',
                'name' => 'Aanvraag ontvangen',
                'subject' => 'Wij hebben uw aanvraag ontvangen',
                'moment' => 'Direct nadat een aanvraag binnenkomt',
                'blocks' => [
                    ['type' => 'tekst', 'body' => "Beste {{contactpersoon}},\n\nBedankt voor uw aanvraag. Wij nemen binnen één werkdag contact met u op om uw wensen door te nemen."],
                    ['type' => 'afsluiting', 'body' => "Met vriendelijke groet,\n{{bedrijf_naam}}\n{{bedrijf_telefoon}}"],
                ],
            ],
            [
                'key' => 'opname-bevestiging',
                'name' => 'Bevestiging van de opname',
                'subject' => 'Uw afspraak voor de opname',
                'moment' => 'Zodra de opname is ingepland',
                'blocks' => [
                    ['type' => 'tekst', 'body' => "Beste {{contactpersoon}},\n\nWij komen bij u langs om de situatie op te nemen."],
                    ['type' => 'gegevens', 'body' => "Adres: {{project_adres}}\nDatum: {{startdatum}}"],
                    ['type' => 'tekst', 'body' => 'Houdt u er rekening mee dat wij de ruimte willen kunnen bekijken en opmeten.'],
                    ['type' => 'afsluiting', 'body' => "Met vriendelijke groet,\n{{bedrijf_naam}}"],
                ],
            ],
            [
                'key' => 'offerte-verstuurd',
                'name' => 'Offerte verstuurd',
                'subject' => 'Uw offerte {{offertenummer}}',
                'moment' => 'Bij het versturen van een offerte',
                'blocks' => [
                    ['type' => 'tekst', 'body' => "Beste {{contactpersoon}},\n\nHierbij onze offerte voor {{project_naam}}."],
                    ['type' => 'gegevens', 'body' => "Offertenummer: {{offertenummer}}\nBedrag: {{offertebedrag}}"],
                    ['type' => 'knop', 'body' => 'Offerte bekijken en ondertekenen|{{offertelink}}'],
                    ['type' => 'tekst', 'body' => 'Heeft u vragen of wilt u iets aangepast zien? Laat het ons weten, wij denken graag mee.'],
                    ['type' => 'afsluiting', 'body' => "Met vriendelijke groet,\n{{bedrijf_naam}}"],
                ],
            ],
            [
                'key' => 'offerte-opvolging',
                'name' => 'Offerte opvolgen',
                'subject' => 'Heeft u nog vragen over uw offerte?',
                'moment' => 'Drie dagen na het versturen, als er niets is gehoord',
                'blocks' => [
                    ['type' => 'tekst', 'body' => "Beste {{contactpersoon}},\n\nEnkele dagen geleden stuurden wij u offerte {{offertenummer}}. Heeft u vragen, of wilt u iets aangepast zien?"],
                    ['type' => 'knop', 'body' => 'Offerte openen|{{offertelink}}'],
                    ['type' => 'afsluiting', 'body' => "Met vriendelijke groet,\n{{bedrijf_naam}}\n{{bedrijf_telefoon}}"],
                ],
            ],
            [
                'key' => 'opdracht-bevestiging',
                'name' => 'Opdracht bevestigd',
                'subject' => 'Bedankt voor uw opdracht',
                'moment' => 'Nadat de klant de offerte heeft getekend',
                'blocks' => [
                    ['type' => 'kop', 'body' => 'Wij gaan voor u aan de slag'],
                    ['type' => 'tekst', 'body' => "Beste {{contactpersoon}},\n\nBedankt voor uw opdracht. Wij hebben uw project ingepland."],
                    ['type' => 'gegevens', 'body' => "Project: {{project_naam}}\nAanneemsom: {{aanneemsom}}\nStart: {{startdatum}}\nVerwachte oplevering: {{opleverdatum}}\nUw projectleider: {{projectleider}}"],
                    ['type' => 'tekst', 'body' => 'U kunt de voortgang volgen in het klantportaal. Daar vindt u ook de planning en de fotos van het werk.'],
                    ['type' => 'afsluiting', 'body' => "Met vriendelijke groet,\n{{bedrijf_naam}}"],
                ],
            ],
            [
                'key' => 'factuur',
                'name' => 'Factuur versturen',
                'subject' => 'Factuur {{factuurnummer}}',
                'moment' => 'Bij het versturen van een termijnfactuur',
                'blocks' => [
                    ['type' => 'tekst', 'body' => "Beste {{contactpersoon}},\n\nHierbij factuur {{factuurnummer}} voor {{project_naam}}."],
                    ['type' => 'gegevens', 'body' => "Bedrag: {{factuurbedrag}}\nVervaldatum: {{vervaldatum}}"],
                    ['type' => 'afsluiting', 'body' => "Met vriendelijke groet,\n{{bedrijf_naam}}"],
                ],
            ],
            [
                'key' => 'oplevering',
                'name' => 'Oplevering en garantie',
                'subject' => 'Uw project is opgeleverd',
                'moment' => 'Na de oplevering',
                'blocks' => [
                    ['type' => 'kop', 'body' => 'Uw project is klaar'],
                    ['type' => 'tekst', 'body' => "Beste {{contactpersoon}},\n\n{{project_naam}} is opgeleverd. In het klantportaal vindt u het opleverrapport met de fotos en de garantievoorwaarden."],
                    ['type' => 'lijst', 'body' => "Merkt u later iets op? Meld het via het portaal of bel ons.\nWij nemen binnen één werkdag contact op.\nValt het onder garantie, dan lossen wij het kosteloos op."],
                    ['type' => 'afsluiting', 'body' => "Met vriendelijke groet,\n{{bedrijf_naam}}\n{{bedrijf_telefoon}}"],
                ],
            ],
        ];
    }
}
