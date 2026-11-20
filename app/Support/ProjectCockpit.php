<?php

namespace App\Support;

use App\Enums\PhaseStatus;
use App\Enums\ProjectStatus;
use App\Models\ExtraWork;
use App\Models\Project;
use App\Services\CostReviewService;
use Illuminate\Support\Carbon;

/**
 * Wat de projectcockpit bovenaan een dossier toont, naast de vier stoplichten:
 * de mijlpalen op een tijdlijn, wat het project tegenhoudt en het budget
 * tegenover de voortgang. Opbouw en regels komen uit het prototype.
 */
class ProjectCockpit
{
    /** Na de oplevering loopt de onderhoudstermijn nog zoveel dagen door. */
    public const MAINTENANCE_DAYS = 90;

    /**
     * @param  list<array{label: string, date: Carbon, done: bool, late: bool, position: float}>  $milestones
     * @param  list<array{text: string, owner: string, tone: string, icon: string, url: ?string}>  $blockers
     * @param  array<string, mixed>|null  $costs
     */
    private function __construct(
        public readonly array $milestones,
        public readonly ?float $today,
        public readonly array $blockers,
        public readonly ?array $costs,
    ) {}

    public static function for(Project $project): self
    {
        $milestones = self::milestones($project);
        $today = null;

        if (count($milestones) > 1) {
            $first = $milestones[0]['date'];
            $last = $milestones[count($milestones) - 1]['date'];
            $span = max(1, $first->diffInDays($last));

            foreach ($milestones as $index => $milestone) {
                $milestones[$index]['position'] = round(min(100, max(0, $first->diffInDays($milestone['date']) / $span * 100)), 2);
            }

            if (today()->betweenIncluded($first, $last)) {
                $today = round($first->diffInDays(today()) / $span * 100, 2);
            }
        }

        return new self(
            $milestones,
            $today,
            self::blockers($project),
            app(CostReviewService::class)->for($project),
        );
    }

    /** Hoeveel van de tijdlijn al achter ons ligt, voor de gekleurde lijn. */
    public function elapsed(): float
    {
        if ($this->today !== null) {
            return $this->today;
        }

        return $this->milestones !== [] && $this->milestones[0]['date']->isFuture() ? 0.0 : 100.0;
    }

    /** Hoeveel van het begrote geld al is uitgegeven, in procenten. */
    public function burn(): ?int
    {
        if ($this->costs === null) {
            return null;
        }

        return (int) round($this->costs['actual'] / max(1, $this->costs['budgeted']) * 100);
    }

    /**
     * De vaste momenten van een project: akkoord, start, tussencontrole op
     * ruim de helft, oplevering en het einde van de onderhoudstermijn.
     *
     * @return list<array{label: string, date: Carbon, done: bool, late: bool, position: float}>
     */
    private static function milestones(Project $project): array
    {
        $out = [];
        $add = function (string $label, ?Carbon $date, ?bool $done = null) use (&$out) {
            if ($date === null) {
                return;
            }

            $done ??= $date->lte(today());
            $out[] = ['label' => $label, 'date' => $date->copy()->startOfDay(), 'done' => $done, 'late' => ! $done && $date->lt(today()), 'position' => 0.0];
        };

        $quote = $project->quote;

        if ($quote?->accepted_at !== null) {
            $add('Akkoord', $quote->accepted_at);
        } elseif ($quote?->sent_at !== null) {
            $add('Offerte verstuurd', $quote->sent_at);
        } elseif ($project->order_confirmed_at !== null) {
            $add('Opdracht', Carbon::parse($project->order_confirmed_at));
        }

        $start = $project->start_date;
        $end = $project->end_date_expected;
        $delivered = in_array($project->status, [ProjectStatus::Afgerond], true) || $project->end_date_actual !== null;

        $add('Start uitvoering', $start);

        if ($start !== null && $end !== null && $end->gt($start)) {
            $add('Tussencontrole', $start->copy()->addDays((int) round($start->diffInDays($end) * 0.55)), (int) $project->progress >= 55);
        }

        if ($end !== null) {
            $add('Oplevering', $end, $delivered);
            $maintenance = $end->copy()->addDays(self::MAINTENANCE_DAYS);
            $add('Einde onderhoudstermijn', $maintenance, $delivered && $maintenance->lte(today()));
        }

        usort($out, fn (array $a, array $b) => $a['date'] <=> $b['date']);

        return $out;
    }

    /**
     * Wat het project nu tegenhoudt, met wie aan zet is.
     *
     * @return list<array{text: string, owner: string, tone: string, icon: string, url: ?string}>
     */
    private static function blockers(Project $project): array
    {
        $out = [];
        $leader = $project->projectLeader?->name ? str($project->projectLeader->name)->before(' ')->toString() : 'Projectleider';

        if (! $project->hasOrder()) {
            $out[] = ['text' => 'Nog geen opdracht: de offerte is niet getekend', 'owner' => $leader, 'tone' => 'bad', 'icon' => 'document-text', 'url' => route('projects.show', [$project, 'tab' => 'offerte'])];
        }

        foreach ($project->phases as $phase) {
            [$text, $owner, $tone, $icon] = match ($phase->status) {
                PhaseStatus::Geblokkeerd => ['Geblokkeerd: '.$phase->name, $phase->responsible?->name ?? $leader, 'bad', 'exclamation-triangle'],
                PhaseStatus::WachtOpKlant => ['Wacht op klant: '.$phase->name, 'Klant', 'warn', 'clock'],
                PhaseStatus::WachtOpLeverancier => ['Wacht op leverancier: '.$phase->name, $phase->responsible?->name ?? $leader, 'warn', 'truck'],
                default => [null, null, null, null],
            };

            if ($text !== null) {
                $out[] = ['text' => $text, 'owner' => $owner, 'tone' => $tone, 'icon' => $icon, 'url' => route('projects.show', [$project, 'tab' => 'uitvoering'])];
            }
        }

        foreach ($project->invoices()->overdue()->get() as $invoice) {
            $out[] = [
                'text' => sprintf('Factuur %s vervallen sinds %s (€ %s)', $invoice->number, $invoice->due_date->translatedFormat('j M'), number_format((float) $invoice->amount, 0, ',', '.')),
                'owner' => 'Administratie', 'tone' => 'bad', 'icon' => 'currency-euro',
                'url' => route('projects.show', [$project, 'tab' => 'financien']),
            ];
        }

        foreach ($project->extraWorks()->where('status', ExtraWork::STATUS_SENT)->get() as $extra) {
            $out[] = [
                'text' => 'Meerwerk '.$extra->number.' wacht op akkoord van de klant',
                'owner' => 'Klant', 'tone' => 'warn', 'icon' => 'clock',
                'url' => route('projects.show', [$project, 'tab' => 'meerwerk']),
            ];
        }

        return $out;
    }
}
