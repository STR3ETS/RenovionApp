<?php

namespace App\Services;

use App\Enums\DocumentCategory;
use App\Enums\HealthLight;
use App\Enums\PhaseStatus;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\WorkPackage;
use App\Support\HealthSignal;
use App\Support\ProjectHealth;
use Illuminate\Database\Eloquent\Collection;

/**
 * De vier stoplichten van de projectcockpit (briefing hoofdstuk 7): planning,
 * budget, kwaliteit en klant. Ze gelden vanaf fase 4 (Uitvoering); daarvoor
 * staat alles op grijs. Het totaal is altijd het slechtste licht.
 *
 * De regels staan hier, niet in de views: de frontend toont alleen uitkomsten.
 */
class ProjectHealthService
{
    /** Vanaf deze fasepositie (Uitvoering) gelden de stoplichten. */
    public const FROM_PHASE = 4;

    /** Vanaf deze fasepositie horen er opleverstukken in het dossier te zitten. */
    private const DELIVERY_PHASE = 7;

    /** Vanaf deze fasepositie tellen openstaande werkpakketten als restpunt. */
    private const LEFTOVER_PHASE = 6;

    /**
     * Alles wat de berekening nodig heeft, zodat het portfolio geen query per rij doet.
     *
     * @var list<string>
     */
    public const RELATIONS = ['phases', 'workPackages', 'documents', 'lead', 'quote', 'craftsmen', 'scheduleEntries'];

    public function for(Project $project): ProjectHealth
    {
        $project->loadMissing(self::RELATIONS);

        return $this->compute($project);
    }

    /**
     * Stoplichten voor een hele lijst projecten in een paar queries.
     *
     * @param  Collection<int, Project>  $projects
     * @return array<int, ProjectHealth>
     */
    public function forMany(Collection $projects): array
    {
        $projects->loadMissing(self::RELATIONS);

        return $projects->mapWithKeys(fn (Project $project) => [$project->id => $this->compute($project)])->all();
    }

    private function compute(Project $project): ProjectHealth
    {
        $phase = $project->currentPhase();
        $position = $phase?->position ?? 0;
        $progress = (int) $project->progress;
        $expected = $this->expectedProgress($project);
        $next = $this->nextMilestone($project);

        // Voor fase 4 zijn de stoplichten nog niet van toepassing.
        if ($position < self::FROM_PHASE) {
            return new ProjectHealth(
                signals: [
                    'planning' => $this->grey('planning', 'Planning', 'calendar'),
                    'budget' => $this->grey('budget', 'Budget', 'currency-euro'),
                    'kwaliteit' => $this->grey('kwaliteit', 'Kwaliteit', 'clipboard-check'),
                    'klant' => $this->grey('klant', 'Klant', 'users'),
                ],
                overall: HealthLight::Grijs,
                expectedProgress: $expected,
                progress: $progress,
                biggestBlocker: null,
                nextMilestone: $next?->name,
                nextMilestoneDue: $next?->planned_end?->translatedFormat('j M Y'),
            );
        }

        $signals = [
            'planning' => $this->planning($project, $progress, $expected),
            'budget' => $this->budget($project),
            'kwaliteit' => $this->quality($project, $position),
            'klant' => $this->client($project),
        ];

        return new ProjectHealth(
            signals: $signals,
            overall: HealthLight::worst(...array_map(fn (HealthSignal $signal) => $signal->light, array_values($signals))),
            expectedProgress: $expected,
            progress: $progress,
            biggestBlocker: $this->biggestBlocker($signals),
            nextMilestone: $next?->name,
            nextMilestoneDue: $next?->planned_end?->translatedFormat('j M Y'),
        );
    }

    /**
     * Waar het project volgens de planning zou moeten staan: hoeveel procent
     * van de looptijd is verstreken. Null als start of einde ontbreekt.
     */
    public function expectedProgress(Project $project): ?int
    {
        $start = $project->start_date;
        $end = $project->end_date_expected;

        if ($start === null || $end === null || $end->lessThanOrEqualTo($start)) {
            return null;
        }

        $total = max(1, (int) $start->diffInDays($end));
        $passed = (int) $start->diffInDays(today(), false);

        return (int) round(max(0.0, min(1.0, $passed / $total)) * 100);
    }

    private function planning(Project $project, int $progress, ?int $expected): HealthSignal
    {
        $behind = $expected === null ? null : $expected - $progress;

        $overdue = $project->end_date_expected !== null
            && $project->end_date_expected->isPast()
            && $project->end_date_actual === null;

        if ($overdue) {
            return $this->signal('planning', 'Planning', 'calendar', HealthLight::Rood,
                'Opleverdatum van '.$project->end_date_expected->translatedFormat('j M').' is voorbij en het project is niet opgeleverd.');
        }

        if ($behind !== null && $behind >= 10) {
            return $this->signal('planning', 'Planning', 'calendar', HealthLight::Rood,
                $behind.'% achter op planning: zou op '.$expected.'% moeten staan, staat op '.$progress.'%.');
        }

        if ($behind !== null && $behind >= 5) {
            return $this->signal('planning', 'Planning', 'calendar', HealthLight::Oranje,
                $behind.'% achter op planning: zou op '.$expected.'% moeten staan, staat op '.$progress.'%.');
        }

        if ($this->startsSoonWithoutTeam($project)) {
            return $this->signal('planning', 'Planning', 'calendar', HealthLight::Oranje,
                'Start op '.$project->start_date->translatedFormat('j M').' en er is nog niemand ingepland.');
        }

        return $this->signal('planning', 'Planning', 'calendar', HealthLight::Groen,
            $expected === null ? 'Loopt, geen afwijking bekend.' : 'Op schema, '.$progress.'% gereed.');
    }

    private function startsSoonWithoutTeam(Project $project): bool
    {
        if ($project->start_date === null || $project->start_date->isPast()) {
            return false;
        }

        if (today()->diffInDays($project->start_date, false) > 10) {
            return false;
        }

        return $project->craftsmen->isEmpty() && $project->scheduleEntries->isEmpty();
    }

    /**
     * Budget. De volledige regel uit de briefing (verwachte kostenafwijking en
     * marge) vraagt om nacalculatie met geboekte uren en inkoopfacturen; die
     * module bestaat nog niet. Tot die er is kijken we naar wat wel vastligt:
     * vervallen termijnen en een ontbrekende aanbetaling.
     */
    private function budget(Project $project): HealthSignal
    {
        $outstanding = $project->outstandingAmount();

        $overdueDays = $project->next_payment_due_at !== null
            && $project->next_payment_due_at->isPast()
            && $outstanding > 0
                ? (int) $project->next_payment_due_at->diffInDays(today())
                : 0;

        $depositMissing = $project->depositOutstanding();
        $started = $project->start_date !== null && $project->start_date->isPast();

        if ($overdueDays > 14) {
            return $this->signal('budget', 'Budget', 'currency-euro', HealthLight::Rood,
                'Termijnfactuur staat '.$overdueDays.' dagen open, nog '.$this->euro($outstanding).' te ontvangen.');
        }

        if ($depositMissing && $started) {
            return $this->signal('budget', 'Budget', 'currency-euro', HealthLight::Rood,
                'Het werk is gestart terwijl de aanbetaling van '.$this->euro((float) $project->deposit_amount).' nog niet binnen is.');
        }

        if ($overdueDays > 0) {
            return $this->signal('budget', 'Budget', 'currency-euro', HealthLight::Oranje,
                'Termijnfactuur is '.$overdueDays.' '.($overdueDays === 1 ? 'dag' : 'dagen').' vervallen.');
        }

        if ($depositMissing) {
            return $this->signal('budget', 'Budget', 'currency-euro', HealthLight::Oranje,
                'Aanbetaling van '.$this->euro((float) $project->deposit_amount).' is nog niet ontvangen.');
        }

        return $this->signal('budget', 'Budget', 'currency-euro', HealthLight::Groen,
            $outstanding > 0 ? $this->euro($outstanding).' open, niets vervallen.' : 'Volledig gefactureerd en betaald.');
    }

    private function quality(Project $project, int $position): HealthSignal
    {
        $blocked = $project->workPackages->first(fn (WorkPackage $package) => $package->status === PhaseStatus::Geblokkeerd);
        $blockedPhase = $project->phases->first(fn (ProjectPhase $phase) => $phase->status === PhaseStatus::Geblokkeerd);
        $leftovers = $this->leftovers($project);
        $missing = $this->missingDocuments($position, $project);

        if ($blocked !== null || $blockedPhase !== null) {
            return $this->signal('kwaliteit', 'Kwaliteit', 'clipboard-check', HealthLight::Rood,
                'Geblokkeerd: '.($blocked->name ?? $blockedPhase->name).'.');
        }

        if ($leftovers > 3) {
            return $this->signal('kwaliteit', 'Kwaliteit', 'clipboard-check', HealthLight::Rood,
                $leftovers.' restpunten open in de oplevering.');
        }

        if (count($missing) > 1) {
            return $this->signal('kwaliteit', 'Kwaliteit', 'clipboard-check', HealthLight::Rood,
                'Dossier incompleet: '.implode(' en ', $missing).' ontbreken.');
        }

        if ($leftovers > 0) {
            return $this->signal('kwaliteit', 'Kwaliteit', 'clipboard-check', HealthLight::Oranje,
                $leftovers.' '.($leftovers === 1 ? 'restpunt' : 'restpunten').' open.');
        }

        if (count($missing) === 1) {
            return $this->signal('kwaliteit', 'Kwaliteit', 'clipboard-check', HealthLight::Oranje,
                'Dossierstuk ontbreekt: '.$missing[0].'.');
        }

        return $this->signal('kwaliteit', 'Kwaliteit', 'clipboard-check', HealthLight::Groen,
            'Geen afwijkingen of restpunten, dossier compleet.');
    }

    /**
     * Restpunten: werkpakketten die nog openstaan terwijl het project al in
     * de vooroplevering of later zit.
     */
    private function leftovers(Project $project): int
    {
        $late = $project->phases
            ->filter(fn (ProjectPhase $phase) => $phase->position >= self::LEFTOVER_PHASE)
            ->pluck('id');

        return $project->workPackages
            ->filter(fn (WorkPackage $package) => $package->status !== PhaseStatus::Gereed && $late->contains($package->project_phase_id))
            ->count();
    }

    /**
     * Verplichte dossierstukken per fase (briefing hoofdstuk 7), getoetst op
     * de documentcategorieen die het systeem nu kent.
     *
     * @return list<string>
     */
    private function missingDocuments(int $position, Project $project): array
    {
        $required = ['aannemingsovereenkomst' => DocumentCategory::Contract];

        if ($position >= self::DELIVERY_PHASE) {
            $required['opleverformulier'] = DocumentCategory::Oplevering;
        }

        $present = $project->documents->pluck('category');

        return array_values(array_keys(array_filter(
            $required,
            fn (DocumentCategory $category) => ! $present->contains($category),
        )));
    }

    private function client(Project $project): HealthSignal
    {
        $quote = $project->quote;

        // Een vraag van de klant op de offerte die 2 dagen of langer openstaat.
        if ($quote !== null && filled($quote->change_request) && $quote->status->isOpen()) {
            $days = (int) $quote->updated_at->diffInDays(now());

            if ($days >= 2) {
                return $this->signal('klant', 'Klant', 'users', HealthLight::Rood,
                    'Vraag van de klant op offerte '.$quote->number.' staat '.$days.' dagen open.');
            }
        }

        $waiting = $project->phases->first(fn (ProjectPhase $phase) => $phase->status === PhaseStatus::WachtOpKlant);

        if ($waiting !== null) {
            return $this->signal('klant', 'Klant', 'users', HealthLight::Oranje,
                'Wacht op de klant bij '.$waiting->name.'.');
        }

        // Zonder vastgelegd contactmoment rekenen we vanaf de aanmaak van het project.
        $lastContact = $project->lead?->last_contact_at ?? $project->created_at;
        $days = (int) $lastContact->diffInDays(now());

        if ($days > 14) {
            return $this->signal('klant', 'Klant', 'users', HealthLight::Oranje,
                $days.' dagen geen contact vastgelegd met de klant.');
        }

        return $this->signal('klant', 'Klant', 'users', HealthLight::Groen,
            'Laatste contact '.$lastContact->diffForHumans().'.');
    }

    /**
     * De grootste blokkade in gewone taal, voor het portfolio en de kaarten.
     *
     * @param  array<string, HealthSignal>  $signals
     */
    private function biggestBlocker(array $signals): ?string
    {
        $worst = null;

        foreach ($signals as $signal) {
            if ($signal->light->severity() >= HealthLight::Oranje->severity()
                && ($worst === null || $signal->light->severity() > $worst->light->severity())) {
                $worst = $signal;
            }
        }

        return $worst?->reason;
    }

    private function nextMilestone(Project $project): ?ProjectPhase
    {
        return $project->phases->first(fn (ProjectPhase $phase) => $phase->status !== PhaseStatus::Gereed);
    }

    private function signal(string $key, string $label, string $icon, HealthLight $light, string $reason): HealthSignal
    {
        return new HealthSignal($key, $label, $icon, $light, $reason);
    }

    private function grey(string $key, string $label, string $icon): HealthSignal
    {
        return $this->signal($key, $label, $icon, HealthLight::Grijs, 'Telt mee zodra het project in uitvoering is.');
    }

    private function euro(float $amount): string
    {
        return 'EUR '.number_format($amount, 0, ',', '.');
    }
}
