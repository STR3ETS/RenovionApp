<?php

namespace App\Services;

use Anthropic\Client;
use App\Enums\ActionSource;
use App\Enums\ScheduleEntryType;
use App\Enums\TaskPriority;
use App\Enums\TimelineEventType;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ScheduleEntry;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Nova volgt het patroon uit de technische briefing (§24):
 * tekst/voice → intent → gestructureerde JSON-actie → validatie → preview/bevestiging → uitvoeren → audit log.
 * Nova voert nooit acties uit zonder expliciete bevestiging van de gebruiker.
 */
class NovaAssistant
{
    /**
     * @var array<int, string>
     */
    public const ACTIONS = ['create_task', 'create_appointment', 'create_note'];

    public function isConfigured(): bool
    {
        return filled(config('renovion.ai.api_key'));
    }

    /**
     * Herken de intentie en stel een actie voor (zonder uit te voeren).
     *
     * @return array{type: 'answer', text: string}|array{type: 'proposal', action: array{type: string, params: array<string, mixed>}, preview: string}
     */
    public function propose(string $message, User $user): array
    {
        $client = new Client(apiKey: (string) config('renovion.ai.api_key'));

        $response = $client->messages->create(
            model: (string) config('renovion.ai.model'),
            maxTokens: 1024,
            outputConfig: ['effort' => 'low'],
            system: [[
                'type' => 'text',
                'text' => $this->systemPrompt($user),
            ]],
            tools: $this->tools(),
            messages: [['role' => 'user', 'content' => $message]],
        );

        foreach ($response->content as $block) {
            if ($block->type === 'tool_use' && in_array($block->name, self::ACTIONS, true)) {
                $action = ['type' => $block->name, 'params' => (array) $block->input];

                $validator = $this->validator($action);

                if ($validator->fails()) {
                    return [
                        'type' => 'answer',
                        'text' => 'Dat kan ik nog niet klaarzetten: '.$validator->errors()->first().' Kun je het iets specifieker maken?',
                    ];
                }

                return [
                    'type' => 'proposal',
                    'action' => $action,
                    'preview' => $this->preview($action),
                ];
            }
        }

        foreach ($response->content as $block) {
            if ($block->type === 'text' && filled($block->text)) {
                return ['type' => 'answer', 'text' => trim($block->text)];
            }
        }

        return ['type' => 'answer', 'text' => 'Dat heb ik niet goed begrepen. Kun je het anders formuleren?'];
    }

    /**
     * Nova schrijft de dagelijkse briefing voor het Vandaag-scherm (briefing §3).
     */
    public function dailyBriefing(User $user): string
    {
        $client = new Client(apiKey: (string) config('renovion.ai.api_key'));

        $signalen = (new AttentionService)->items()
            ->take(12)
            ->map(fn (array $item) => strtoupper($item['severity']).' | '.$item['label'].' | '.$item['title'].($item['subtitle'] ? ' ('.$item['subtitle'].')' : ''))
            ->implode("\n");

        $planning = ScheduleEntry::whereDate('date', today())
            ->with(['user', 'project', 'customer'])
            ->get()
            ->map(fn (ScheduleEntry $entry) => ($entry->start_time ? substr($entry->start_time, 0, 5).' ' : '').$entry->displayTitle().' ('.$entry->user->name.')')
            ->implode("\n");

        $response = $client->messages->create(
            model: (string) config('renovion.ai.model'),
            maxTokens: 400,
            outputConfig: ['effort' => 'low'],
            system: [[
                'type' => 'text',
                'text' => "Je bent Nova, de AI-assistent van renovatiebedrijf Renovion. Vandaag is het {$this->today()}. "
                    .'Schrijf de dagelijkse briefing voor het dashboard, gericht aan '.str($user->name)->before(' ').'. '
                    .'Maximaal 4 korte regels, in volgorde van urgentie, direct en concreet ("bel", "controleer", "volg op"). '
                    .'Geen begroeting en geen afsluiting — die staan al op het scherm. Geen opsommingstekens of emoji. '
                    .'Als er niets speelt, zeg je dat alles op schema loopt.',
            ]],
            messages: [['role' => 'user', 'content' => "SIGNALEN (urgentie | type | omschrijving):\n".($signalen ?: 'geen')."\n\nPLANNING VANDAAG:\n".($planning ?: 'niets gepland')]],
        );

        foreach ($response->content as $block) {
            if ($block->type === 'text' && filled($block->text)) {
                return trim($block->text);
            }
        }

        return 'Vandaag geen bijzonderheden — alles loopt op schema.';
    }

    /**
     * Voer een bevestigde actie uit.
     *
     * @param  array{type: string, params: array<string, mixed>}  $action
     * @return array{message: string, url: string|null}
     */
    public function execute(array $action, User $user, ActionSource $source = ActionSource::Nova): array
    {
        $validated = $this->validator($action)->validate();

        return DB::transaction(fn () => match ($action['type']) {
            'create_task' => $this->executeCreateTask($validated, $user, $source),
            'create_appointment' => $this->executeCreateAppointment($validated, $user, $source),
            'create_note' => $this->executeCreateNote($validated, $user, $source),
        });
    }

    /**
     * @param  array{type: string, params: array<string, mixed>}  $action
     */
    public function validator(array $action): \Illuminate\Validation\Validator
    {
        $rules = match ($action['type']) {
            'create_task' => [
                'title' => ['required', 'string', 'max:255'],
                'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
                'project_id' => ['nullable', 'integer', 'exists:projects,id'],
                'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
                'owner_id' => ['nullable', 'integer', 'exists:users,id'],
                'deadline' => ['nullable', 'date'],
                'priority' => ['nullable', Rule::enum(TaskPriority::class)],
                'note' => ['nullable', 'string', 'max:2000'],
            ],
            'create_appointment' => [
                'user_id' => ['required', 'integer', 'exists:users,id'],
                'date' => ['required', 'date'],
                'start_time' => ['nullable', 'date_format:H:i'],
                'end_time' => ['nullable', 'date_format:H:i'],
                'type' => ['required', Rule::enum(ScheduleEntryType::class)],
                'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
                'project_id' => ['nullable', 'integer', 'exists:projects,id'],
                'title' => ['nullable', 'string', 'max:255'],
            ],
            'create_note' => [
                'customer_id' => ['required', 'integer', 'exists:customers,id'],
                'body' => ['required', 'string', 'max:5000'],
            ],
            default => [],
        };

        return Validator::make($action['params'] ?? [], $rules);
    }

    /**
     * Mensleesbare preview van de voorgestelde actie, voor de bevestigingsstap.
     *
     * @param  array{type: string, params: array<string, mixed>}  $action
     */
    public function preview(array $action): string
    {
        $params = $action['params'];
        $customer = isset($params['customer_id']) ? Customer::find($params['customer_id']) : null;
        $project = isset($params['project_id']) ? Project::find($params['project_id']) : null;

        return match ($action['type']) {
            'create_task' => collect([
                'Taak: "'.$params['title'].'"',
                $customer?->name ? 'klant: '.$customer->name : null,
                $project?->name ? 'project: '.$project->name : null,
                isset($params['deadline']) ? 'deadline: '.Carbon::parse($params['deadline'])->translatedFormat('D j M') : null,
                isset($params['priority']) ? 'prioriteit: '.$params['priority'] : null,
                isset($params['owner_id']) ? 'voor: '.User::find($params['owner_id'])?->name : null,
            ])->filter()->implode(' · '),
            'create_appointment' => collect([
                ScheduleEntryType::from($params['type'])->label().' inplannen',
                'voor: '.User::find($params['user_id'])?->name,
                'op '.Carbon::parse($params['date'])->translatedFormat('l j F'),
                isset($params['start_time']) ? 'om '.$params['start_time'] : null,
                $customer?->name ? 'met '.$customer->name : null,
                $project?->name ? 'project: '.$project->name : null,
                isset($params['title']) ? '— '.$params['title'] : null,
            ])->filter()->implode(' · '),
            'create_note' => 'Notitie bij '.($customer?->name ?? 'klant').': "'.str($params['body'])->limit(120).'"',
            default => 'Onbekende actie',
        };
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{message: string, url: string|null}
     */
    private function executeCreateTask(array $params, User $user, ActionSource $source): array
    {
        if (empty($params['customer_id'])) {
            $params['customer_id'] = match (true) {
                ! empty($params['project_id']) => Project::find($params['project_id'])?->customer_id,
                ! empty($params['lead_id']) => Lead::find($params['lead_id'])?->customer_id,
                default => null,
            };
        }

        $task = Task::create([
            'title' => $params['title'],
            'note' => $params['note'] ?? null,
            'customer_id' => $params['customer_id'] ?? null,
            'project_id' => $params['project_id'] ?? null,
            'lead_id' => $params['lead_id'] ?? null,
            'owner_id' => $params['owner_id'] ?? $user->id,
            'deadline' => $params['deadline'] ?? null,
            'priority' => $params['priority'] ?? TaskPriority::Normaal,
            'source' => $source,
        ]);

        AuditLog::record($task, 'aangemaakt', [], ['title' => $task->title], $source);
        $task->customer?->recordEvent(TimelineEventType::Intern, 'Taak aangemaakt via Nova: '.$task->title, null, $task, $source);

        return ['message' => 'Taak aangemaakt: "'.$task->title.'".', 'url' => route('tasks.index')];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{message: string, url: string|null}
     */
    private function executeCreateAppointment(array $params, User $user, ActionSource $source): array
    {
        if (empty($params['customer_id']) && ! empty($params['project_id'])) {
            $params['customer_id'] = Project::find($params['project_id'])?->customer_id;
        }

        $entry = ScheduleEntry::create([
            'user_id' => $params['user_id'],
            'project_id' => $params['project_id'] ?? null,
            'customer_id' => $params['customer_id'] ?? null,
            'type' => $params['type'],
            'title' => $params['title'] ?? null,
            'date' => $params['date'],
            'start_time' => $params['start_time'] ?? null,
            'end_time' => $params['end_time'] ?? null,
        ]);

        AuditLog::record($entry, 'aangemaakt', [], ['date' => $params['date']], $source);
        $entry->customer?->recordEvent(
            TimelineEventType::Afspraak,
            $entry->type->label().' ingepland via Nova op '.$entry->date->translatedFormat('j M').($entry->start_time ? ' om '.substr($entry->start_time, 0, 5) : ''),
            null,
            $entry,
            $source,
        );

        return [
            'message' => $entry->type->label().' ingepland op '.$entry->date->translatedFormat('l j F').($entry->start_time ? ' om '.substr($entry->start_time, 0, 5) : '').'.',
            'url' => route('planning.index', ['week' => $entry->date->toDateString()]),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{message: string, url: string|null}
     */
    private function executeCreateNote(array $params, User $user, ActionSource $source): array
    {
        $customer = Customer::findOrFail($params['customer_id']);
        $customer->recordEvent(TimelineEventType::Notitie, 'Notitie via Nova', $params['body'], null, $source);

        return ['message' => 'Notitie toegevoegd aan het dossier van '.$customer->name.'.', 'url' => route('customers.show', $customer)];
    }

    private function systemPrompt(User $user): string
    {
        $customers = Customer::orderBy('name')->limit(150)->get(['id', 'name', 'city', 'phone'])
            ->map(fn (Customer $c) => "{$c->id} | {$c->name} | ".($c->city ?? '-'))->implode("\n");

        $users = User::orderBy('name')->get(['id', 'name', 'role'])
            ->map(fn (User $u) => "{$u->id} | {$u->name} | {$u->role->label()}")->implode("\n");

        $projects = Project::active()->with('customer:id,name')->get(['id', 'name', 'customer_id', 'status'])
            ->map(fn (Project $p) => "{$p->id} | {$p->name} | klant_id {$p->customer_id} | {$p->status->label()}")->implode("\n");

        $leads = Lead::open()->with('customer:id,name')->get(['id', 'customer_id', 'service', 'status'])
            ->map(fn (Lead $l) => "{$l->id} | {$l->customer->name} | ".($l->service ?? '-')." | {$l->status->label()}")->implode("\n");

        return <<<PROMPT
            Je bent Nova, de AI-assistent van Renovion Dash — het CRM- en projectmanagementsysteem van renovatiebedrijf Renovion uit Arnhem.
            Je praat met {$user->name} ({$user->role->label()}). Antwoord altijd in het Nederlands, kort en to-the-point.
            Vandaag is het {$this->today()}.

            Jouw taak: herken wat de gebruiker wil en zet er een actie voor klaar met een van je tools (taak aanmaken, afspraak/terugbelafspraak inplannen, notitie toevoegen).
            De gebruiker bevestigt of annuleert het voorstel — jij voert zelf nooit iets definitief uit.

            Regels:
            - Gebruik de juiste id's uit de onderstaande context. Verzin nooit id's.
            - Herken relatieve datums ("morgen", "vrijdag", "volgende week dinsdag") en reken ze om naar YYYY-MM-DD op basis van vandaag.
            - Een "terugbelafspraak" is type "terugbel"; een intake is type "intake"; overleg of bezoek is "afspraak".
            - Bij onduidelijkheid (welke klant, welke datum) vraag je kort terug in plaats van te gokken.
            - Informatieve vragen over klanten, projecten of planning beantwoord je direct op basis van de context hieronder.
            - Vragen buiten Renovion Dash (nieuws, algemene kennis) wimpel je vriendelijk af.

            MEDEWERKERS (id | naam | rol):
            {$users}

            KLANTEN (id | naam | plaats):
            {$customers}

            LOPENDE PROJECTEN (id | naam | klant_id | status):
            {$projects}

            OPEN AANVRAGEN (id | klant | dienst | status):
            {$leads}
            PROMPT;
    }

    private function today(): string
    {
        return now()->translatedFormat('l j F Y, H:i');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tools(): array
    {
        return [
            [
                'name' => 'create_task',
                'description' => 'Zet een taak klaar. Gebruik dit wanneer de gebruiker iets wil onthouden, controleren of laten doen (bijv. "zet een taak om de aanbetaling te controleren").',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'Korte, actieve taakomschrijving in het Nederlands'],
                        'customer_id' => ['type' => 'integer', 'description' => 'Id van de klant waar de taak bij hoort'],
                        'project_id' => ['type' => 'integer', 'description' => 'Id van het project waar de taak bij hoort'],
                        'lead_id' => ['type' => 'integer', 'description' => 'Id van de aanvraag waar de taak bij hoort'],
                        'owner_id' => ['type' => 'integer', 'description' => 'Id van de medewerker die de taak krijgt; laat weg voor de huidige gebruiker'],
                        'deadline' => ['type' => 'string', 'description' => 'Deadline als YYYY-MM-DD'],
                        'priority' => ['type' => 'string', 'enum' => ['laag', 'normaal', 'hoog']],
                        'note' => ['type' => 'string', 'description' => 'Extra toelichting'],
                    ],
                    'required' => ['title'],
                ],
            ],
            [
                'name' => 'create_appointment',
                'description' => 'Plan een afspraak, terugbelafspraak, intake of projectdag in de planning. Gebruik dit bij "plan", "zet in de agenda", "terugbellen om ...".',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'user_id' => ['type' => 'integer', 'description' => 'Id van de medewerker voor wie dit wordt ingepland'],
                        'date' => ['type' => 'string', 'description' => 'Datum als YYYY-MM-DD'],
                        'start_time' => ['type' => 'string', 'description' => 'Starttijd als HH:MM (24-uurs)'],
                        'end_time' => ['type' => 'string', 'description' => 'Eindtijd als HH:MM'],
                        'type' => ['type' => 'string', 'enum' => ['afspraak', 'terugbel', 'intake', 'project', 'overig']],
                        'customer_id' => ['type' => 'integer', 'description' => 'Id van de klant'],
                        'project_id' => ['type' => 'integer', 'description' => 'Id van het project'],
                        'title' => ['type' => 'string', 'description' => 'Korte omschrijving, bijv. "Terugbelafspraak familie Jansen"'],
                    ],
                    'required' => ['user_id', 'date', 'type'],
                ],
            ],
            [
                'name' => 'create_note',
                'description' => 'Voeg een notitie toe aan de timeline van een klantdossier. Gebruik dit bij "maak een notitie bij ..." of "noteer bij ...".',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'customer_id' => ['type' => 'integer', 'description' => 'Id van de klant'],
                        'body' => ['type' => 'string', 'description' => 'De inhoud van de notitie'],
                    ],
                    'required' => ['customer_id', 'body'],
                ],
            ],
        ];
    }
}
