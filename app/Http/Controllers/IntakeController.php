<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Enums\ScheduleEntryType;
use App\Enums\TimelineEventType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Intake;
use App\Models\Lead;
use App\Models\ScheduleEntry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Intake in vijf stappen. De score laat zien welke antwoorden nog ontbreken, en
 * Nova stelt de vragen die er bij dit soort werk nog horen.
 */
class IntakeController extends Controller
{
    public function index(Request $request): View
    {
        $intakes = Intake::with(['lead.customer', 'customer', 'surveyor', 'project'])
            ->latest('updated_at')
            ->get();

        // Aanvragen die nog geen intake hebben: daar begint het.
        $withoutIntake = Lead::open()
            ->whereDoesntHave('intake')
            ->with('customer')
            ->orderByDesc('created_at')
            ->get();

        return view('intakes.index', [
            'open' => $intakes->whereNull('completed_at'),
            'done' => $intakes->whereNotNull('completed_at')->take(10),
            'withoutIntake' => $withoutIntake,
            'steps' => Intake::STEPS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lead_id' => ['required', 'exists:leads,id'],
        ]);

        $lead = Lead::with('customer')->findOrFail($validated['lead_id']);

        $intake = Intake::firstOrCreate(
            ['lead_id' => $lead->id],
            [
                'customer_id' => $lead->customer_id,
                'step' => 1,
                'answers' => [],
                'created_by' => $request->user()->id,
            ],
        );

        if ($intake->wasRecentlyCreated && $lead->status === LeadStatus::Nieuw) {
            $lead->update(['status' => LeadStatus::Contact]);
        }

        return redirect()->route('intakes.show', $intake)
            ->with('success', 'Intake geopend. Loop de stappen langs.');
    }

    public function show(Request $request, Intake $intake): View
    {
        $intake->load(['lead.customer', 'customer', 'project', 'surveyor']);

        $step = min(count(Intake::STEPS) - 1, max(0, $request->integer('stap', $intake->step)));

        return view('intakes.show', [
            'intake' => $intake,
            'step' => $step,
            'fields' => Intake::fieldsForStep($step),
            'completeness' => $intake->completeness(),
            'questions' => $intake->suggestedQuestions(),
            'surveyors' => User::query()
                ->where('active', true)
                ->whereIn('role', [
                    UserRole::Projectleider->value,
                    UserRole::Werkvoorbereider->value,
                    UserRole::Calculator->value,
                    UserRole::Sales->value,
                ])
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Antwoorden opslaan. Alles komt per stap binnen, zodat je nooit een heel
     * formulier hoeft af te maken voordat je iets kwijt kunt.
     */
    public function update(Request $request, Intake $intake): RedirectResponse
    {
        $validated = $request->validate([
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable', 'string', 'max:5000'],
            'step' => ['nullable', 'integer', 'min:0', 'max:4'],
            'summary' => ['nullable', 'string', 'max:5000'],
        ]);

        $answers = $intake->answers ?? [];

        foreach ($validated['answers'] ?? [] as $field => $value) {
            if (! array_key_exists($field, Intake::FIELDS)) {
                continue;
            }

            $answers[$field] = filled($value) ? trim($value) : null;
        }

        $before = $intake->completeness()['percentage'];

        $intake->update([
            'answers' => $answers,
            'step' => max($intake->step, $validated['step'] ?? $intake->step),
            'summary' => $validated['summary'] ?? $intake->summary,
        ]);

        $after = $intake->fresh()->completeness()['percentage'];

        if ($after !== $before) {
            AuditLog::record($intake, 'intake bijgewerkt', ['volledigheid' => $before], ['volledigheid' => $after]);
        }

        $next = ($validated['step'] ?? $intake->step) + 1;

        return redirect()
            ->route('intakes.show', [$intake, 'stap' => min(count(Intake::STEPS) - 1, $next)])
            ->with('success', "Opgeslagen. De intake is nu {$after} procent compleet.");
    }

    /**
     * De opname inplannen: dat zet meteen een afspraak in de planning, zodat de
     * opnemer hem op zijn eigen scherm ziet staan.
     */
    public function planSurvey(Request $request, Intake $intake): RedirectResponse
    {
        $validated = $request->validate([
            'surveyor_id' => ['required', 'exists:users,id'],
            'survey_on' => ['required', 'date'],
            'survey_at' => ['nullable', 'date_format:H:i'],
        ]);

        $intake->update([...$validated, 'step' => max($intake->step, 3)]);

        $customer = $intake->customer ?? $intake->lead?->customer;

        ScheduleEntry::updateOrCreate(
            [
                'user_id' => $validated['surveyor_id'],
                'lead_id' => $intake->lead_id,
                'type' => ScheduleEntryType::Intake,
            ],
            [
                'customer_id' => $customer?->id,
                'title' => 'Opname '.($customer?->name ?? 'aanvraag'),
                'date' => $validated['survey_on'],
                'start_time' => $validated['survey_at'] ?? null,
            ],
        );

        if ($intake->lead?->status === LeadStatus::Contact) {
            $intake->lead->update(['status' => LeadStatus::Intake]);
        }

        $customer?->recordEvent(
            TimelineEventType::Afspraak,
            'Opname gepland op '.Carbon::parse($validated['survey_on'])->translatedFormat('j F'),
            'Met '.User::find($validated['surveyor_id'])?->name.($validated['survey_at'] ?? null ? ', '.$validated['survey_at'] : ''),
            $intake,
        );

        AuditLog::record($intake, 'opname gepland', [], [
            'datum' => $validated['survey_on'],
            'opnemer' => User::find($validated['surveyor_id'])?->name,
        ]);

        return back()->with('success', 'Opname gepland en in de planning gezet.');
    }

    /**
     * De intake afronden. Dat lukt niet met ontbrekende kernvragen: dan ga je met
     * een half beeld rekenen en klopt de calculatie later niet.
     */
    public function complete(Request $request, Intake $intake): RedirectResponse
    {
        $completeness = $intake->completeness();

        if ($completeness['missing'] !== []) {
            return back()->with('error', 'Nog niet compleet. Dit mist: '.implode(', ', $completeness['missing']).'.');
        }

        $intake->update(['completed_at' => now(), 'step' => 4]);

        if ($intake->lead !== null && $intake->lead->status === LeadStatus::Contact) {
            $intake->lead->update(['status' => LeadStatus::Intake]);
        }

        AuditLog::record($intake, 'intake afgerond', [], ['volledigheid' => $completeness['percentage']]);

        return back()->with('success', 'Intake afgerond. De calculator kan aan de slag.');
    }
}
