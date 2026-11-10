<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * De certificaten die Renovion bijhoudt, met de afkorting voor het chipje, de
 * gebruikelijke geldigheidsduur in jaren en waar het over gaat.
 *
 * Een verlopen certificaat is geen administratief detail: wie zonder geldige VCA
 * de bouwplaats op gaat, is een risico voor zichzelf en voor de opdrachtgever.
 */
class CertificateCatalogue
{
    /** @var array<string, array{short: string, years: int, about: string, colour: string}> */
    public const TYPES = [
        'VCA-basis' => ['short' => 'VCA', 'years' => 10, 'about' => 'Veiligheid basis', 'colour' => 'amber'],
        'VCA-VOL' => ['short' => 'VOL', 'years' => 10, 'about' => 'Veiligheid leidinggevende', 'colour' => 'brand'],
        'BHV' => ['short' => 'BHV', 'years' => 1, 'about' => 'Bedrijfshulpverlening', 'colour' => 'red'],
        'EHBO' => ['short' => 'EHBO', 'years' => 1, 'about' => 'Eerste hulp', 'colour' => 'green'],
        'NEN 3140 (VOP)' => ['short' => 'NEN', 'years' => 3, 'about' => 'Elektrisch werken', 'colour' => 'lead'],
        'Erkend gasinstallateur' => ['short' => 'GAS', 'years' => 5, 'about' => 'Gasinstallaties', 'colour' => 'klant'],
        'Valbeveiliging' => ['short' => 'VAL', 'years' => 2, 'about' => 'Werken op hoogte', 'colour' => 'calc'],
        'Hoogwerker' => ['short' => 'HW', 'years' => 5, 'about' => 'Hoogwerker bedienen', 'colour' => 'lead'],
        'DAV asbest' => ['short' => 'ASB', 'years' => 3, 'about' => 'Asbestverwijdering', 'colour' => 'slate'],
        'Rijbewijs BE' => ['short' => 'BE', 'years' => 10, 'about' => 'Rijden met aanhanger', 'colour' => 'slate'],
        'Wkb-kwaliteitsborger' => ['short' => 'WKB', 'years' => 3, 'about' => 'Kwaliteitsborging', 'colour' => 'planning'],
    ];

    /** De vakken waarop we mensen en partners inzetten. */
    public const TRADES = [
        'sloopwerk', 'ruwbouw', 'metselwerk', 'timmerwerk', 'dakwerk', 'kozijnen',
        'stucwerk', 'tegelwerk', 'schilderwerk', 'vloeren', 'loodgieterswerk',
        'elektra', 'verwarming', 'ventilatie', 'keukens', 'badkamers', 'bestrating',
    ];

    /** Dossierstukken die een onderaannemer moet hebben voordat hij mag werken. */
    public const PARTNER_DOCUMENTS = [
        'KvK-uittreksel' => 'Uittreksel Kamer van Koophandel',
        'VCA-certificaat' => 'VCA van de mensen die komen',
        'Aansprakelijkheidsverzekering' => 'Geldige AVB-polis',
        'G-rekening' => 'G-rekening voor de ketenaansprakelijkheid',
    ];

    /** Overeenkomsten die ondertekend moeten zijn. */
    public const PARTNER_AGREEMENTS = [
        'Modelovereenkomst (DBA)' => 'Voorkomt een vermoeden van loondienst',
        'Raamovereenkomst onderaanneming' => 'De algemene afspraken',
        'Verwerkersovereenkomst (AVG)' => 'Voor het uitwisselen van klantgegevens',
        'V&G-instructie Renovion' => 'Veiligheidsinstructie gelezen en begrepen',
    ];

    /** Vanaf hoeveel dagen vooruit we waarschuwen dat iets verloopt. */
    public const WARN_DAYS = 30;

    /**
     * @return array{short: string, years: int, about: string, colour: string}
     */
    public static function meta(string $type): array
    {
        return self::TYPES[$type] ?? [
            'short' => mb_strtoupper(mb_substr($type, 0, 3)),
            'years' => 3,
            'about' => '',
            'colour' => 'slate',
        ];
    }

    /**
     * De staat van iets met een geldigheidsdatum: geldig, verloopt binnenkort,
     * verlopen, of helemaal niet aanwezig.
     */
    public static function state(?\DateTimeInterface $validUntil, bool $present = true): string
    {
        if (! $present) {
            return 'ontbreekt';
        }

        if ($validUntil === null) {
            return 'ok';
        }

        $date = Carbon::instance($validUntil);

        return match (true) {
            $date->isPast() => 'verlopen',
            $date->lessThanOrEqualTo(today()->addDays(self::WARN_DAYS)) => 'binnenkort',
            default => 'ok',
        };
    }

    public static function stateLabel(string $state): string
    {
        return match ($state) {
            'verlopen' => 'Verlopen',
            'binnenkort' => 'Verloopt binnenkort',
            'ontbreekt' => 'Ontbreekt',
            default => 'In orde',
        };
    }

    public static function stateClasses(string $state): string
    {
        return match ($state) {
            'verlopen', 'ontbreekt' => 'bg-red-500/15 text-red-300',
            'binnenkort' => 'bg-amber-500/15 text-amber-300',
            default => 'bg-green-500/15 text-green-300',
        };
    }

    public static function chipClasses(string $colour): string
    {
        return match ($colour) {
            'brand' => 'bg-brand-500/15 text-brand-300',
            'red' => 'bg-red-500/15 text-red-300',
            'green' => 'bg-green-500/15 text-green-300',
            'amber' => 'bg-amber-500/15 text-amber-300',
            'lead' => 'bg-lead-500/15 text-lead-300',
            'calc' => 'bg-calc-500/15 text-calc-300',
            'klant' => 'bg-klant-500/15 text-klant-300',
            'planning' => 'bg-planning-500/15 text-planning-300',
            default => 'bg-white/10 text-slate-300',
        };
    }
}
