<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Http\Controllers\IntakeController as WebIntakeController;
use App\Models\Intake;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Intakes in de app (intakes/index en intakes/show). Starten, antwoorden opslaan, opname plannen en afronden
 * gaan via de IntakeController van de website (zelfde regels), die JSON geeft aan de app.
 */
class IntakeController extends Controller
{
    public function index(): JsonResponse
    {
        $intakes = Intake::with(['lead.customer', 'customer', 'surveyor', 'project'])
            ->latest('updated_at')
            ->get();

        $withoutIntake = Lead::open()
            ->whereDoesntHave('intake')
            ->with('customer')
            ->orderByDesc('created_at')
            ->get();

        $steps = count(Intake::STEPS);

        return response()->json([
            'without' => [
                'count' => $withoutIntake->count(),
                'leads' => $withoutIntake->take(8)->values()->map(fn (Lead $lead) => [
                    'id' => $lead->id,
                    'name' => $lead->customer?->name ?? 'Onbekende klant',
                    'sub' => ($lead->service ?: 'geen dienst opgegeven').', binnengekomen '.$lead->created_at->translatedFormat('j M'),
                ]),
            ],
            'open' => $intakes->whereNull('completed_at')->values()->map(function (Intake $intake) use ($steps) {
                $score = $intake->completeness();

                return [
                    'id' => $intake->id,
                    'name' => $this->name($intake),
                    'sub' => 'Stap '.($intake->step + 1)." van {$steps}, ".$intake->stepLabel()
                        .($intake->survey_on ? ', opname '.$intake->survey_on->translatedFormat('j M').' met '.$intake->surveyor?->name : ''),
                    'step' => $intake->step,
                    'percentage' => $score['percentage'],
                    'missing' => $score['missing'] === [] ? null
                        : 'Mist nog: '.collect($score['missing'])->take(3)->join(', ').(count($score['missing']) > 3 ? ' en meer' : '').'.',
                ];
            }),
            'done' => $intakes->whereNotNull('completed_at')->take(10)->values()->map(fn (Intake $intake) => [
                'id' => $intake->id,
                'name' => $this->name($intake),
                'sub' => 'afgerond '.$intake->completed_at->translatedFormat('j M Y'),
            ]),
            'steps' => array_map(fn (array $step) => $step[0], Intake::STEPS),
        ]);
    }

    public function show(Intake $intake): JsonResponse
    {
        $intake->load(['lead.customer', 'customer', 'project', 'surveyor']);
        $customer = $intake->customer ?? $intake->lead?->customer;
        $completeness = $intake->completeness();
        // Een time-kolom komt als 12:30:00 terug; het tijdveld en de validatie (H:i) willen 12:30.
        $surveyAt = $intake->survey_at ? substr((string) $intake->survey_at, 0, 5) : null;

        return response()->json([
            'id' => $intake->id,
            'name' => $customer?->name ?? 'Intake',
            'subtitle' => collect([$customer?->address, $customer?->city, $intake->lead?->service])->filter()->join(', '),
            'percentage' => $completeness['percentage'],
            'missing' => $completeness['missing'],
            'complete' => $intake->isComplete(),
            'step' => $intake->step,
            'steps' => array_map(fn (array $step) => ['label' => $step[0], 'hint' => $step[1]], Intake::STEPS),
            'fields' => collect(Intake::FIELDS)->map(fn (array $field, string $key) => [
                'key' => $key,
                'label' => $field['label'],
                'hint' => $field['hint'],
                'step' => $field['step'],
            ])->values(),
            'answers' => collect(Intake::FIELDS)->mapWithKeys(fn (array $field, string $key) => [$key => $intake->answer($key) ?? ''])->all(),
            'summary' => $intake->summary,
            'customer' => $customer ? [
                'name' => $customer->name,
                'address' => collect([$customer->address, $customer->postcode, $customer->city])->filter()->join(', ') ?: null,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'path' => route('customers.show', $customer, false),
            ] : null,
            'questions' => $intake->suggestedQuestions(),
            'survey' => [
                'surveyor_id' => $intake->surveyor_id,
                'on' => $intake->survey_on?->toDateString(),
                'at' => $surveyAt,
                'text' => $intake->survey_on
                    ? ucfirst($intake->survey_on->translatedFormat('l j F')).($surveyAt ? ', '.$surveyAt : '')
                    : null,
                'who' => $intake->surveyor ? 'met '.$intake->surveyor->name : null,
            ],
            'surveyors' => WebIntakeController::surveyors()->map(fn (User $person) => [
                'id' => $person->id,
                'label' => $person->name.', '.($person->job_title ?: $person->role->label()),
            ]),
            'links' => [
                'lead' => $intake->lead ? route('leads.show', $intake->lead, false) : null,
            ],
        ]);
    }

    private function name(Intake $intake): string
    {
        return $intake->customer?->name ?? $intake->lead?->customer?->name ?? 'Onbekende klant';
    }
}
