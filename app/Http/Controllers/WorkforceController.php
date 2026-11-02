<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\ScheduleEntry;
use App\Models\User;
use App\Support\CertificateCatalogue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Eigen mensen: wie we hebben, met welke certificaten, hoe druk ze deze week
 * zijn en wanneer ze er niet zijn.
 */
class WorkforceController extends Controller
{
    public function index(Request $request): View
    {
        $week = $request->date('week')?->startOfWeek() ?? today()->startOfWeek();
        $weekEnd = $week->copy()->addDays(4);

        $people = User::query()
            ->where('active', true)
            ->whereNotIn('role', collect(UserRole::external())->pluck('value'))
            ->with(['certificates', 'absences' => fn ($q) => $q->where('until', '>=', today())])
            ->orderBy('name')
            ->get();

        // Eén query voor de planning van de hele week.
        $entries = ScheduleEntry::whereIn('user_id', $people->pluck('id'))
            ->where(fn ($q) => $q
                ->whereBetween('date', [$week, $weekEnd])
                ->orWhere(fn ($sub) => $sub->where('date', '<=', $weekEnd)->where('end_date', '>=', $week)))
            ->with('project')
            ->get()
            ->groupBy('user_id');

        return view('workforce.index', [
            'week' => $week,
            'weekEnd' => $weekEnd,
            'rows' => $people->map(fn (User $person) => [
                'person' => $person,
                'entries' => $entries->get($person->id, collect()),
                'load' => $this->load($person, $entries->get($person->id, collect()), $week, $weekEnd),
                'expiring' => $person->certificates->filter(
                    fn (Certificate $c) => in_array($c->state(), ['verlopen', 'binnenkort'], true),
                ),
            ]),
            'certificateTypes' => CertificateCatalogue::TYPES,
            'trades' => CertificateCatalogue::TRADES,
            'expiringTotal' => Certificate::whereNotNull('valid_until')
                ->whereDate('valid_until', '<=', today()->addDays(CertificateCatalogue::WARN_DAYS))
                ->count(),
        ]);
    }

    /**
     * De bezetting van iemand deze week: geplande uren tegenover de capaciteit,
     * minus de dagen dat die persoon er niet is.
     *
     * @param  Collection<int, ScheduleEntry>  $entries
     * @return array{planned: int, available: int, percentage: int, absent: int}
     */
    private function load(User $person, Collection $entries, Carbon $week, Carbon $weekEnd): array
    {
        // Acht uur per werkdag die het blok binnen deze week beslaat.
        $planned = $entries->sum(function (ScheduleEntry $entry) use ($week, $weekEnd) {
            $start = $entry->date->greaterThan($week) ? $entry->date : $week;
            $end = ($entry->end_date ?? $entry->date)->lessThan($weekEnd)
                ? ($entry->end_date ?? $entry->date)
                : $weekEnd;

            return $start->greaterThan($end) ? 0 : ((int) $start->diffInWeekdays($end) + 1) * 8;
        });

        $absentDays = $person->absences->sum(fn (Absence $absence) => $absence->workdaysIn($week, $weekEnd));
        $available = max(0, $person->weekly_capacity - $absentDays * 8);

        return [
            'planned' => (int) $planned,
            'available' => $available,
            'percentage' => $available > 0 ? (int) round($planned / $available * 100) : 0,
            'absent' => (int) $absentDays,
        ];
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->can('manage-team'), 403);

        $validated = $request->validate([
            'job_title' => ['nullable', 'string', 'max:120'],
            'trades' => ['nullable', 'array'],
            'trades.*' => ['string', 'max:60'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'weekly_capacity' => ['nullable', 'integer', 'min:0', 'max:80'],
            'plannable' => ['nullable', 'boolean'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $validated['plannable'] = $request->boolean('plannable');

        $old = $user->only(array_keys($validated));
        $user->update($validated);

        AuditLog::record($user, 'personeelsgegevens bijgewerkt', $old, $validated);

        return back()->with('success', "Gegevens van {$user->name} bijgewerkt.");
    }

    public function storeCertificate(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->can('manage-team'), 403);

        $validated = $request->validate([
            'type' => ['required', 'string', 'max:120'],
            'number' => ['nullable', 'string', 'max:80'],
            'issued_on' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
        ]);

        // Zonder einddatum rekenen we met de gebruikelijke geldigheidsduur.
        if (blank($validated['valid_until'] ?? null) && filled($validated['issued_on'] ?? null)) {
            $years = CertificateCatalogue::meta($validated['type'])['years'];
            $validated['valid_until'] = Carbon::parse($validated['issued_on'])->addYears($years);
        }

        $user->certificates()->create($validated);

        return back()->with('success', "Certificaat toegevoegd aan {$user->name}.");
    }

    public function destroyCertificate(Request $request, Certificate $certificate): RedirectResponse
    {
        abort_unless($request->user()->can('manage-team'), 403);

        $certificate->delete();

        return back()->with('success', 'Certificaat verwijderd.');
    }

    public function storeAbsence(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->can('manage-crm'), 403);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:vakantie,ziekte,verlof,opleiding'],
            'from' => ['required', 'date'],
            'until' => ['required', 'date', 'after_or_equal:from'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $user->absences()->create($validated);

        return back()->with('success', "Afwezigheid vastgelegd voor {$user->name}.");
    }

    public function destroyAbsence(Request $request, Absence $absence): RedirectResponse
    {
        abort_unless($request->user()->can('manage-crm'), 403);

        $absence->delete();

        return back()->with('success', 'Afwezigheid verwijderd.');
    }
}
