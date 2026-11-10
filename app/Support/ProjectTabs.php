<?php

namespace App\Support;

use App\Enums\PhaseStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Project;
use App\Models\User;

/**
 * De tabbladen van een projectdossier, zoals in het prototype: één brede
 * pillenbalk waarin het hele project langskomt, van aanvraag tot audit.
 *
 * Een tabblad verschijnt alleen als de gebruiker er iets te zoeken heeft. Een
 * uitvoerder hoeft geen financiën te zien, en zonder de offertemodule bestaat
 * dat tabblad niet.
 */
class ProjectTabs
{
    /**
     * key => [label, module uit de rechtenmatrix of null voor iedereen].
     *
     * @var array<string, array{0: string, 1: ?string}>
     */
    public const TABS = [
        'overzicht' => ['Overzicht', null],
        'intake' => ['Aanvraag en intake', 'intake'],
        'begroting' => ['Begroting', 'begrotingen'],
        'offerte' => ['Offerte', 'offertes'],
        'planning' => ['Planning', 'planning'],
        'uitvoering' => ['Uitvoering', null],
        'taken' => ['Taken', 'taken'],
        'werkbonnen' => ['Werkbonnen', 'werkbonnen'],
        'fotos' => ["Foto's", null],
        'inkoop' => ['Inkoop en partners', 'inkoop'],
        'kwaliteit' => ['Kwaliteit', 'kwaliteit'],
        'financien' => ['Financiën', 'facturatie'],
        'nacalculatie' => ['Nacalculatie', 'nacalculatie'],
        'documenten' => ['Documenten', 'documenten'],
        'activiteiten' => ['Activiteiten', null],
        'agents' => ['Agents en audit', 'agents'],
    ];

    /**
     * De tabbladen die deze gebruiker mag zien, met per tabblad een teller als
     * daar iets open staat.
     *
     * @return list<array{key: string, label: string, count: int, active: bool}>
     */
    public static function forUser(User $user, Project $project, string $current): array
    {
        $counts = self::counts($project);
        $out = [];

        foreach (self::TABS as $key => [$label, $module]) {
            if ($module !== null && ! Navigation::allows($user->role, $module)) {
                continue;
            }

            $out[] = [
                'key' => $key,
                'label' => $label,
                'count' => $counts[$key] ?? 0,
                'active' => $key === $current,
            ];
        }

        return $out;
    }

    /** Het eerste tabblad dat deze gebruiker mag zien. */
    public static function resolve(User $user, Project $project, ?string $wanted): string
    {
        $allowed = collect(self::forUser($user, $project, ''))->pluck('key');

        return $allowed->contains($wanted) ? $wanted : 'overzicht';
    }

    /**
     * Tellers die op het tabblad zelf staan: open taken, openstaande restpunten
     * en werkbonnen die op goedkeuring wachten.
     *
     * @return array<string, int>
     */
    private static function counts(Project $project): array
    {
        return [
            'taken' => $project->tasks()->open()->count(),
            'werkbonnen' => $project->workOrders()
                ->where('status', WorkOrderStatus::Controle)
                ->count(),
            'kwaliteit' => $project->snags()->open()->count(),
            'fotos' => $project->photos()->count(),
        ];
    }

    /**
     * De grote oranje knop in de kop: die wijst naar de volgende stap in het
     * proces, zodat je vanaf het dossier verder kunt zonder te zoeken.
     *
     * @return array{label: string, tab: string}
     */
    public static function nextStep(Project $project): array
    {
        $phase = $project->phases->firstWhere('status', '!=', PhaseStatus::Gereed);
        $position = $phase?->position ?? 8;

        return match (true) {
            $position <= 1 => ['label' => 'Naar de begroting', 'tab' => 'begroting'],
            $position <= 2 => ['label' => 'Naar de offerte', 'tab' => 'offerte'],
            $position <= 4 => ['label' => 'Naar de planning', 'tab' => 'planning'],
            $position <= 6 => ['label' => 'Naar kwaliteit', 'tab' => 'kwaliteit'],
            default => ['label' => 'Naar financiën', 'tab' => 'financien'],
        };
    }
}
