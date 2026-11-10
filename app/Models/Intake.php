<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * De intake in vijf stappen: aanvraag, belgesprek, afspraak voor de opname, de
 * opname ter plaatse en de samenvatting.
 *
 * Dit is het verschil tussen een calculatie die klopt en een die later misgaat.
 * Daarom staat er een score op: welke antwoorden heb je, en welke mis je nog.
 * De zeven velden in REQUIRED zijn de vragen waar een calculator niet zonder
 * kan.
 */
#[Fillable([
    'lead_id', 'project_id', 'customer_id', 'step', 'answers',
    'surveyor_id', 'survey_on', 'survey_at', 'summary', 'completed_at', 'created_by',
])]
class Intake extends Model
{
    /** @var list<array{0: string, 1: string}> */
    public const STEPS = [
        ['Aanvraag', 'Klant en adres'],
        ['Belgesprek', 'Wensen, budget en termijn'],
        ['Afspraak opname', 'Wanneer en wie gaat'],
        ['Opname ter plaatse', 'Situatie, maten en fotos'],
        ['Samenvatting', 'Nova en het vervolg'],
    ];

    /**
     * De vijftien velden van de intake, met per veld in welke stap hij hoort.
     *
     * @var array<string, array{label: string, step: int, hint: string}>
     */
    public const FIELDS = [
        'wensen' => ['label' => 'Wensen en gewenst eindresultaat', 'step' => 1, 'hint' => 'Wat wil de klant bereiken?'],
        'ruimtes' => ['label' => 'Ruimtes die worden aangepast', 'step' => 1, 'hint' => 'Welke ruimtes, en hoeveel'],
        'budget' => ['label' => 'Budgetindicatie', 'step' => 1, 'hint' => 'Waar denkt de klant aan?'],
        'beslissers' => ['label' => 'Wie beslist mee', 'step' => 1, 'hint' => 'Partner, VvE, verhuurder'],
        'startWens' => ['label' => 'Gewenste start', 'step' => 1, 'hint' => 'Wanneer wil de klant beginnen?'],
        'deadline' => ['label' => 'Deadline', 'step' => 1, 'hint' => 'Is er een harde datum?'],
        'situatie' => ['label' => 'Bestaande situatie', 'step' => 3, 'hint' => 'Wat zit er nu, en in welke staat'],
        'afmetingen' => ['label' => 'Afmetingen', 'step' => 3, 'hint' => 'Lengte, breedte, hoogte per ruimte'],
        'aandacht' => ['label' => 'Bouwkundige aandachtspunten', 'step' => 3, 'hint' => 'Scheuren, vocht, asbestverdacht materiaal'],
        'constructie' => ['label' => 'Constructie', 'step' => 3, 'hint' => 'Dragende wanden, vloeropbouw'],
        'installaties' => ['label' => 'Installaties', 'step' => 3, 'hint' => 'Waar liggen afvoer, water, elektra en gas'],
        'bereikbaarheid' => ['label' => 'Bereikbaarheid', 'step' => 3, 'hint' => 'Parkeren, verdieping, lift, container'],
        'vergunning' => ['label' => 'Vergunningen of onderzoeken', 'step' => 3, 'hint' => 'Omgevingsloket, VvE, monument'],
        'bijzonder' => ['label' => 'Bijzondere omstandigheden', 'step' => 3, 'hint' => 'Huisdieren, thuiswerken, kinderen'],
        'kwalificatie' => ['label' => 'Hoe kansrijk is dit?', 'step' => 4, 'hint' => 'Koud, warm of heet, en waarom'],
    ];

    /** Zonder deze antwoorden kan een calculator niet rekenen. */
    public const REQUIRED = [
        'wensen', 'budget', 'startWens', 'situatie', 'afmetingen', 'aandacht', 'bereikbaarheid',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'survey_on' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function surveyor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surveyor_id');
    }

    public function answer(string $field): ?string
    {
        $value = $this->answers[$field] ?? null;

        return filled($value) ? $value : null;
    }

    /**
     * De volledigheid: hoeveel van de verplichte vragen zijn beantwoord, plus het
     * telefoonnummer van de klant. Zonder nummer kun je geen afspraak maken.
     *
     * @return array{percentage: int, missing: list<string>}
     */
    public function completeness(): array
    {
        $missing = array_values(array_filter(
            self::REQUIRED,
            fn (string $field) => $this->answer($field) === null,
        ));

        if (blank($this->customer?->phone)) {
            array_unshift($missing, 'telefoonnummer van de klant');
        }

        $total = count(self::REQUIRED) + 1;

        return [
            'percentage' => (int) round(($total - count($missing)) / $total * 100),
            'missing' => array_map(
                fn (string $field) => self::FIELDS[$field]['label'] ?? $field,
                $missing,
            ),
        ];
    }

    /**
     * De vragen die Nova nog zou stellen. Ze volgen uit wat ontbreekt en uit het
     * soort werk: bij een badkamer wil je weten waar de afvoer ligt, bij een
     * aanbouw of de vergunningsplicht gecheckt is.
     *
     * @return list<string>
     */
    public function suggestedQuestions(): array
    {
        $questions = [];
        $type = mb_strtolower((string) ($this->project?->type ?? $this->lead?->service ?? ''));
        $context = mb_strtolower((string) ($this->answer('aandacht').' '.$this->answer('installaties')));

        if ($this->answer('afmetingen') === null) {
            $questions[] = 'Wat zijn de exacte afmetingen, in lengte, breedte en hoogte?';
        }

        if (str_contains($type, 'badkamer')) {
            if (! str_contains($context, 'afvoer')) {
                $questions[] = 'Waar ligt de huidige afvoer, en kan de douche verplaatst worden?';
            }

            $questions[] = 'Moet er tijdens de verbouwing een tijdelijke douche komen?';
        }

        if (str_contains($type, 'keuken')) {
            $questions[] = 'Wordt er een dragende wand aangepast? Dan is een constructieberekening nodig.';
        }

        if (preg_match('/aanbouw|dakkapel|nieuwbouw|uitbouw/', $type) === 1) {
            $questions[] = 'Is de vergunningsplicht gecontroleerd in het Omgevingsloket?';
        }

        if ($this->answer('bereikbaarheid') === null) {
            $questions[] = 'Hoe is de bereikbaarheid voor materiaal en container: parkeren, verdieping, lift?';
        }

        if ($this->answer('budget') === null) {
            $questions[] = 'Welk budget heeft de klant in gedachten?';
        }

        if ($this->answer('constructie') === null && preg_match('/aanbouw|uitbouw|doorbreken|samenvoegen/', $type) === 1) {
            $questions[] = 'Welke wanden zijn dragend, en is daar een berekening van?';
        }

        return $questions === []
            ? ['Geen aanvullende vragen. Deze intake is rond, je kunt de begroting inplannen.']
            : $questions;
    }

    /**
     * De velden die bij een stap horen, zodat het formulier per stap alleen
     * vraagt wat op dat moment aan de orde is.
     *
     * @return array<string, array{label: string, step: int, hint: string}>
     */
    public static function fieldsForStep(int $step): array
    {
        return array_filter(self::FIELDS, fn (array $field) => $field['step'] === $step);
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }

    public function stepLabel(): string
    {
        return self::STEPS[$this->step][0] ?? 'Intake';
    }
}
