<?php

namespace App\Support;

use App\Enums\LeadStatus;
use App\Enums\ProjectStatus;
use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Quote;
use Illuminate\Support\Facades\Route;

/**
 * Eén dossier van aanvraag tot nazorg. In het prototype is een klus vanaf de
 * eerste aanvraag een projectdossier; bij ons zijn aanvraag, offerte en project
 * aparte records. Deze klasse legt ze naast elkaar, zodat de aanvraag, de
 * offerte en het project dezelfde kop, dezelfde negen stappen en dezelfde
 * tabbalk tonen.
 */
class Dossier
{
    /** De negen stappen van het dossier, in volgorde. */
    public const STEPS = [
        'Aanvraag', 'Intake', 'Begroting', 'Offerte', 'Akkoord',
        'Voorbereiding', 'Uitvoering', 'Oplevering', 'Nazorg',
    ];

    private function __construct(
        public readonly ?Lead $lead,
        public readonly ?Quote $quote,
        public readonly ?Project $project,
        public readonly ?Customer $customer,
    ) {}

    public static function forLead(Lead $lead): self
    {
        $quote = $lead->quotes()->latest('id')->first();

        return new self($lead, $quote, $lead->project, $lead->customer);
    }

    public static function forQuote(Quote $quote): self
    {
        $lead = $quote->lead;

        return new self($lead, $quote, $quote->project ?? $lead?->project, $quote->customer);
    }

    public static function forProject(Project $project): self
    {
        return new self($project->lead, $project->quote, $project, $project->customer);
    }

    /** Het nummer waaronder het dossier bekend is: project, anders offerte, anders aanvraag. */
    public function number(): string
    {
        return $this->project?->number()
            ?? $this->quote?->number
            ?? 'A-'.str_pad((string) $this->lead?->id, 4, '0', STR_PAD_LEFT);
    }

    public function title(): string
    {
        return $this->project?->name ?? $this->customer?->name ?? 'Dossier';
    }

    /** Het soort werk, voor het label boven de titel. */
    public function kind(): ?string
    {
        return $this->project?->type ?: $this->lead?->service;
    }

    public function address(): ?string
    {
        $parts = $this->project !== null
            ? [$this->project->address, $this->project->city]
            : [$this->customer?->address, $this->customer?->city];

        return collect($parts)->filter()->join(', ') ?: null;
    }

    /**
     * Bij welke stap het dossier nu is (0 tot en met 8). Het verst gevorderde
     * record bepaalt het: een project wint van een offerte, een offerte van
     * de aanvraag.
     */
    public function stage(): int
    {
        if ($this->project !== null) {
            return match ($this->project->status) {
                ProjectStatus::Uitvoering => 6,
                ProjectStatus::Oplevering => 7,
                ProjectStatus::Afgerond => 8,
                default => 5,
            };
        }

        if ($this->quote !== null) {
            return match ($this->quote->status) {
                QuoteStatus::Akkoord => 4,
                // Een concept is nog rekenwerk: de klant heeft niets gezien.
                QuoteStatus::Concept => 2,
                default => 3,
            };
        }

        return match ($this->lead?->status) {
            LeadStatus::Intake => 1,
            LeadStatus::Offerte => 2,
            LeadStatus::Akkoord => 4,
            LeadStatus::Project => 5,
            default => 0,
        };
    }

    public function stageLabel(): string
    {
        return self::STEPS[$this->stage()];
    }

    /**
     * @return list<array{nr: int, label: string, state: 'done'|'current'|'todo'}>
     */
    public function steps(): array
    {
        $stage = $this->stage();
        // Een afgerond project heeft ook de laatste stap gehad.
        $finished = $this->project?->status === ProjectStatus::Afgerond;

        return array_map(fn (string $label, int $index) => [
            'nr' => $index + 1,
            'label' => $label,
            'state' => $index < $stage || ($finished && $index === $stage) ? 'done' : ($index === $stage ? 'current' : 'todo'),
        ], self::STEPS, array_keys(self::STEPS));
    }

    /**
     * De zes gegevens die altijd in de kop van een dossier staan.
     *
     * @return array<string, string>
     */
    public function meta(): array
    {
        $owner = $this->project?->projectLeader ?? $this->lead?->assignee;
        $value = $this->project?->value ?? $this->quote?->subtotal;

        return [
            'Dossier' => $this->number(),
            'Opdrachtgever' => $this->customer?->name ?? 'onbekend',
            'Fase' => $this->stageLabel(),
            $this->project !== null ? 'Projectleider' : 'Behandelaar' => $owner?->name ?? 'nog niet toegewezen',
            'Waarde' => $value !== null ? '€ '.number_format((float) $value, 2, ',', '.') : 'nog niet bekend',
            'Offerte'.($this->quote ? ' '.$this->quote->number : '') => $this->quote?->status->label() ?? 'nog niet',
        ];
    }

    /**
     * De tabbalk voor een dossier dat nog geen project is: de aanvraag, het
     * rekenwerk en de offerte. Bestaat het project al, dan staat de sprong
     * naar het volledige dossier erbij.
     *
     * @return list<array{label: string, url: string, active: bool}>
     */
    public function tabs(string $active): array
    {
        $tabs = [];

        if ($this->lead !== null) {
            $tabs[] = ['label' => 'Aanvraag en intake', 'url' => route('leads.show', $this->lead), 'active' => $active === 'aanvraag'];
        }

        $calculation = $this->quote?->calculation ?? $this->lead?->calculations()->latest('id')->first();

        if ($calculation !== null && Route::has('calculations.show')) {
            $tabs[] = ['label' => 'Begroting', 'url' => route('calculations.show', $calculation), 'active' => false];
        }

        if ($this->quote !== null && Route::has('quotes.show')) {
            $tabs[] = ['label' => 'Offerte', 'url' => route('quotes.show', $this->quote), 'active' => $active === 'offerte'];
        }

        if ($this->project !== null) {
            $tabs[] = ['label' => 'Projectdossier', 'url' => route('projects.show', $this->project), 'active' => false];
        }

        if ($this->customer !== null) {
            $tabs[] = ['label' => 'Klantdossier', 'url' => route('customers.show', $this->customer), 'active' => false];
        }

        return $tabs;
    }
}
