<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WorkforceController as WebWorkforceController;
use App\Models\Absence;
use App\Models\Certificate;
use App\Models\ScheduleEntry;
use App\Models\User;
use App\Support\App\Badges;
use App\Support\CertificateCatalogue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Eigen mensen in de app (workforce/index): per persoon certificaten, bezetting in de gekozen week, de planning
 * van die week en afwezigheid. Dezelfde berekening als de website (WorkforceController::overview); wijzigen
 * via de WorkforceController van de website. Gegevens en certificaten beheren mag alleen manage-team, net
 * als op de website; afwezigheid iedereen van kantoor.
 */
class WorkforceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $week = $request->date('week')?->startOfWeek() ?? today()->startOfWeek();
        ['weekEnd' => $weekEnd, 'rows' => $rows] = WebWorkforceController::overview($week);
        $team = $request->user()->can('manage-team');

        return response()->json([
            'week' => [
                'start' => $week->toDateString(),
                'label' => 'Week '.$week->isoWeek().', '.$week->translatedFormat('j M').' t/m '.$weekEnd->translatedFormat('j M'),
                'previous' => $week->copy()->subWeek()->toDateString(),
                'next' => $week->copy()->addWeek()->toDateString(),
                'current' => $week->isSameDay(today()->startOfWeek()),
            ],
            'expiring' => WebWorkforceController::expiringTotal(),
            'can_team' => $team,
            'certificate_types' => collect(CertificateCatalogue::TYPES)
                ->map(fn (array $meta, string $type) => ['value' => $type, 'label' => $type.', '.$meta['about']])
                ->values(),
            'absence_types' => collect(Absence::TYPES)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values(),
            'people' => $rows->map(function (array $row) {
                /** @var User $person */
                $person = $row['person'];
                $load = $row['load'];

                return [
                    'id' => $person->id,
                    'name' => $person->name,
                    'initial' => mb_strtoupper(mb_substr($person->name, 0, 1)),
                    'role' => ['label' => $person->job_title ?: $person->role->label(), 'classes' => Badges::role($person->role)],
                    'plannable' => $person->isPlannable(),
                    'certificates' => $person->certificates->map(fn (Certificate $cert) => [
                        'id' => $cert->id,
                        'type' => $cert->type,
                        'short' => $cert->meta()['short'],
                        'chip' => $cert->state() === 'ok' ? Badges::chip($cert->meta()['colour']) : Badges::state($cert->state()),
                        'state' => Badges::state($cert->state()),
                        'until' => $cert->valid_until?->translatedFormat('M Y') ?? 'onbeperkt',
                    ])->values(),
                    'load' => [
                        'planned' => $load['planned'],
                        'available' => $load['available'],
                        'percentage' => $load['percentage'],
                        'absent' => $load['absent'],
                        'tone' => $load['percentage'] > 100 ? 'red' : ($load['percentage'] >= 80 ? 'green' : 'brand'),
                    ],
                    'entries' => $row['entries']->map(fn (ScheduleEntry $entry) => [
                        'id' => $entry->id,
                        'title' => $entry->displayTitle(),
                        'sub' => collect([
                            $entry->project?->name,
                            $entry->date->translatedFormat('j M')
                                .($entry->end_date && ! $entry->end_date->isSameDay($entry->date) ? ' t/m '.$entry->end_date->translatedFormat('j M') : ''),
                        ])->filter()->join(', '),
                    ])->values(),
                    'absences' => $person->absences->map(fn (Absence $absence) => [
                        'id' => $absence->id,
                        'label' => $absence->typeLabel().', '.$absence->from->translatedFormat('j M').' t/m '.$absence->until->translatedFormat('j M'),
                    ])->values(),
                    'form' => [
                        'job_title' => $person->job_title,
                        'hourly_rate' => $person->hourly_rate !== null ? (float) $person->hourly_rate : null,
                        'weekly_capacity' => $person->weekly_capacity,
                        'plannable' => (bool) $person->plannable,
                    ],
                ];
            })->values(),
        ]);
    }
}
