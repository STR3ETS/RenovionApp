<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Instellingen die zonder deploy te wijzigen zijn. Geheimen horen hier niet in:
 * sleutels en tokens blijven in .env.
 */
#[Fillable(['key', 'value', 'group', 'updated_by'])]
class Setting extends Model
{
    private const CACHE_KEY = 'settings-all';

    /**
     * De velden die het instellingenscherm aanbiedt, met per veld in welke groep
     * hij staat en wat de standaardwaarde is.
     *
     * @var array<string, array{label: string, group: string, default: string, hint: string, type: string}>
     */
    public const FIELDS = [
        'bedrijf.naam' => ['label' => 'Bedrijfsnaam', 'group' => 'Bedrijf', 'default' => 'Renovion', 'hint' => '', 'type' => 'text'],
        'bedrijf.adres' => ['label' => 'Adres', 'group' => 'Bedrijf', 'default' => 'Mercatorweg 28', 'hint' => '', 'type' => 'text'],
        'bedrijf.postcode' => ['label' => 'Postcode en plaats', 'group' => 'Bedrijf', 'default' => '6827 DC Arnhem', 'hint' => '', 'type' => 'text'],
        'bedrijf.email' => ['label' => 'E-mailadres', 'group' => 'Bedrijf', 'default' => 'info@renovion.nl', 'hint' => '', 'type' => 'text'],
        'bedrijf.telefoon' => ['label' => 'Telefoonnummer', 'group' => 'Bedrijf', 'default' => '+31 6 395 353 00', 'hint' => '', 'type' => 'text'],
        'bedrijf.kvk' => ['label' => 'KvK-nummer', 'group' => 'Bedrijf', 'default' => '95384782', 'hint' => '', 'type' => 'text'],
        'bedrijf.btw' => ['label' => 'Btw-nummer', 'group' => 'Bedrijf', 'default' => '', 'hint' => '', 'type' => 'text'],
        'bedrijf.iban' => ['label' => 'IBAN', 'group' => 'Bedrijf', 'default' => '', 'hint' => 'Komt op de factuur te staan', 'type' => 'text'],

        'calculatie.uurtarief' => ['label' => 'Standaard uurtarief', 'group' => 'Calculatie', 'default' => '55', 'hint' => 'Waarmee een nieuwe begroting begint', 'type' => 'number'],
        'calculatie.ak' => ['label' => 'Algemene kosten in procenten', 'group' => 'Calculatie', 'default' => '8', 'hint' => '', 'type' => 'number'],
        'calculatie.winst' => ['label' => 'Winst en risico in procenten', 'group' => 'Calculatie', 'default' => '7', 'hint' => '', 'type' => 'number'],
        'calculatie.btw' => ['label' => 'Btw in procenten', 'group' => 'Calculatie', 'default' => '21', 'hint' => '', 'type' => 'number'],

        'facturatie.betaaltermijn' => ['label' => 'Betaaltermijn in dagen', 'group' => 'Facturatie', 'default' => '14', 'hint' => '', 'type' => 'number'],
        'facturatie.aanbetaling' => ['label' => 'Aanbetaling in procenten', 'group' => 'Facturatie', 'default' => '20', 'hint' => 'Bij opdracht', 'type' => 'number'],
        'facturatie.opschorting' => ['label' => 'Opschortingsrecht in procenten', 'group' => 'Facturatie', 'default' => '5', 'hint' => 'Wat een particuliere klant mag achterhouden tot na de onderhoudstermijn', 'type' => 'number'],
        'facturatie.onderhoudstermijn' => ['label' => 'Onderhoudstermijn in maanden', 'group' => 'Facturatie', 'default' => '3', 'hint' => 'Na oplevering, waarna de vijf procent vrijkomt', 'type' => 'number'],

        'offerte.geldigheid' => ['label' => 'Offerte geldig in dagen', 'group' => 'Offertes', 'default' => '30', 'hint' => '', 'type' => 'number'],
        'offerte.opvolging' => ['label' => 'Opvolgen na dagen', 'group' => 'Offertes', 'default' => '3', 'hint' => 'Daarna verschijnt de offerte in de inbox', 'type' => 'number'],
    ];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Alle instellingen, met de standaardwaarde waar niets is ingevuld.
     *
     * @return array<string, string>
     */
    public static function allValues(): array
    {
        return Cache::remember(self::CACHE_KEY, 600, function () {
            $stored = self::pluck('value', 'key');

            return collect(self::FIELDS)
                ->map(fn (array $field, string $key) => $stored[$key] ?? $field['default'])
                ->all();
        });
    }

    public static function get(string $key, ?string $fallback = null): string
    {
        return self::allValues()[$key] ?? $fallback ?? self::FIELDS[$key]['default'] ?? '';
    }

    public static function number(string $key): float
    {
        return (float) str_replace(',', '.', self::get($key));
    }

    public static function put(string $key, ?string $value, ?int $userId = null): void
    {
        if (! array_key_exists($key, self::FIELDS)) {
            return;
        }

        self::updateOrCreate(['key' => $key], [
            'value' => $value,
            'group' => self::FIELDS[$key]['group'],
            'updated_by' => $userId,
        ]);

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * De groepen in de volgorde waarin ze op het scherm staan.
     *
     * @return list<string>
     */
    public static function groups(): array
    {
        return array_values(array_unique(array_column(self::FIELDS, 'group')));
    }
}
