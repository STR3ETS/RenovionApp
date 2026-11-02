<?php

namespace App\Support;

/**
 * De agents van Renovion en wat ze mogen.
 *
 * Nova is de orkestrator; daaronder zit per vakgebied een agent met een eigen
 * specialisme en een eigen menselijke eigenaar. Die eigenaar is geen formaliteit:
 * elke skill heeft een niveau, en bij alles boven "protocol uitvoeren" beslist
 * een mens.
 *
 * De vier niveaus zijn oplopend in wat een agent zelfstandig mag:
 * observeren (lezen en signaleren), voorbereiden (concept maken, mens beslist),
 * protocol uitvoeren (een vastgestelde actie zonder aparte bevestiging) en
 * begrensd wijzigen (wijzigen binnen expliciete grenzen).
 */
class AgentRegistry
{
    /** @var array<string, array{name: string, field: string, owner: string, task: string, colour: string}> */
    public const AGENTS = [
        'nova' => [
            'name' => 'Nova',
            'field' => 'Orkestratie',
            'owner' => 'Operationeel verantwoordelijke',
            'task' => 'Opdrachten begrijpen, routeren, samenvatten, escaleren en de overdracht bewaken',
            'colour' => 'brand',
        ],
        'lex' => [
            'name' => 'Lex',
            'field' => 'Sales en intake',
            'owner' => 'Salesverantwoordelijke',
            'task' => 'Aanvraag oppakken, opname voorbereiden, ontbrekende gegevens opvragen en offertes opvolgen',
            'colour' => 'planning',
        ],
        'cato' => [
            'name' => 'Cato',
            'field' => 'Calculatie en offerte',
            'owner' => 'Bevoegd calculator',
            'task' => 'Hoeveelheden structureren, prijsregels voorstellen en ontbrekende posten signaleren',
            'colour' => 'lead',
        ],
        'prep' => [
            'name' => 'Prep',
            'field' => 'Werkvoorbereiding en inkoop',
            'owner' => 'Werkvoorbereider',
            'task' => 'Tekeningen, keuzes, werkpakketten, materialen en de status bij leveranciers',
            'colour' => 'klant',
        ],
        'milo' => [
            'name' => 'Milo',
            'field' => 'Planning en projectbewaking',
            'owner' => 'Projectleider',
            'task' => 'Afhankelijkheden, capaciteit, deadlines en voorstellen om te herplannen',
            'colour' => 'project',
        ],
        'dex' => [
            'name' => 'Dex',
            'field' => 'Uitvoering en bewijs',
            'owner' => 'Uitvoerder, technische goedkeuring door een specialist',
            'task' => 'Checklists, foto-bewijs koppelen, issues en controleren of het compleet is',
            'colour' => 'amber',
        ],
        'finn' => [
            'name' => 'Finn',
            'field' => 'Budget en administratie',
            'owner' => 'Financieel bevoegde medewerker',
            'task' => 'Uren, kosten, verplichtingen, meerwerk en signalen over termijnen',
            'colour' => 'green',
        ],
        'liv' => [
            'name' => 'Liv',
            'field' => 'Klantzorg en nazorg',
            'owner' => 'Verantwoordelijke klantzorg',
            'task' => 'Klantupdates, kennisantwoorden, restpunten, enquête en reviewopvolging',
            'colour' => 'calc',
        ],
    ];

    /** @var array<string, array{label: string, about: string}> */
    public const LEVELS = [
        'observeren' => ['label' => 'Observeren', 'about' => 'Lezen en signaleren'],
        'voorbereiden' => ['label' => 'Voorbereiden', 'about' => 'Concept maken, een mens beslist'],
        'protocol' => ['label' => 'Protocol uitvoeren', 'about' => 'Een vastgestelde actie zonder aparte bevestiging'],
        'begrensd' => ['label' => 'Begrensd wijzigen', 'about' => 'Wijzigen binnen expliciete grenzen'],
    ];

    /**
     * De skills: wat een agent kan, op welk niveau, en wie het goedkeurt.
     *
     * @var array<string, array{agent: string, name: string, level: string, approver: ?string, input: string, output: string}>
     */
    public const SKILLS = [
        'complete_intake' => [
            'agent' => 'lex', 'level' => 'voorbereiden', 'approver' => 'Sales',
            'name' => 'Intake aanvullen uit gesprekken en documenten',
            'input' => 'aanvraag, notities, voice note, documenten',
            'output' => 'voorgestelde intakevelden met bron en open vragen',
        ],
        'propose_calculation_lines' => [
            'agent' => 'cato', 'level' => 'voorbereiden', 'approver' => 'Calculator',
            'name' => 'Calculatieregels voorstellen',
            'input' => 'bevestigde intake, versie van de prijsbibliotheek',
            'output' => 'calculatieregels met bron en controlepunten',
        ],
        'create_quote_draft' => [
            'agent' => 'cato', 'level' => 'voorbereiden', 'approver' => 'Calculator',
            'name' => 'Conceptofferte maken',
            'input' => 'bevestigde calculatieversie',
            'output' => 'offerteversie plus een goedkeuringsverzoek',
        ],
        'create_project_from_quote' => [
            'agent' => 'milo', 'level' => 'protocol', 'approver' => null,
            'name' => 'Project aanmaken na akkoord',
            'input' => 'een getekende offerte',
            'output' => 'project, werkpakketten en de startgate',
        ],
        'check_material_status' => [
            'agent' => 'prep', 'level' => 'observeren', 'approver' => null,
            'name' => 'Materiaalstatus bewaken',
            'input' => 'inkooporders en planning',
            'output' => 'signaal met de impact op de planning',
        ],
        'propose_schedule_change' => [
            'agent' => 'milo', 'level' => 'voorbereiden', 'approver' => 'Projectleider',
            'name' => 'Herplanning voorstellen',
            'input' => 'incident en beschikbare capaciteit',
            'output' => 'planningsvoorstel met de gevolgen',
        ],
        'send_internal_reminder' => [
            'agent' => 'nova', 'level' => 'protocol', 'approver' => null,
            'name' => 'Interne herinnering sturen',
            'input' => 'taak en eigenaar',
            'output' => 'chatbericht plus taak',
        ],
        'prepare_customer_update' => [
            'agent' => 'liv', 'level' => 'voorbereiden', 'approver' => 'Projectleider',
            'name' => 'Klantupdate voorbereiden',
            'input' => 'klantzichtbare projectdata',
            'output' => 'conceptbericht zonder interne gegevens',
        ],
        'check_work_package_readiness' => [
            'agent' => 'dex', 'level' => 'protocol', 'approver' => 'Uitvoerder, technisch een specialist',
            'name' => 'Gereedmelding van een werkpakket controleren',
            'input' => 'een gereedmelding',
            'output' => 'gatestatus en wat er aan bewijs mist',
        ],
        'classify_evidence' => [
            'agent' => 'dex', 'level' => 'voorbereiden', 'approver' => 'Degene die uploadde',
            'name' => 'Foto-bewijs rubriceren',
            'input' => 'foto met metadata',
            'output' => 'voorstel voor werkpakket, checklistitem en zichtbaarheid',
        ],
        'propose_variation' => [
            'agent' => 'finn', 'level' => 'voorbereiden', 'approver' => 'Projectleider en klant',
            'name' => 'Meerwerkvoorstel opstellen',
            'input' => 'signaal uit chat, opname of foto',
            'output' => 'wijzigingsvoorstel met prijs- en planningsimpact',
        ],
        'budget_forecast' => [
            'agent' => 'finn', 'level' => 'observeren', 'approver' => null,
            'name' => 'Budget en verwachte eindkosten',
            'input' => 'begroting, uren, verplichtingen en facturen',
            'output' => 'prognose zonder dubbeltelling',
        ],
        'prepare_handover_report' => [
            'agent' => 'liv', 'level' => 'voorbereiden', 'approver' => 'Projectleider en klant',
            'name' => 'Opleverrapport voorbereiden',
            'input' => 'scope, bewijs, gates en meerwerk',
            'output' => 'rapportversie met restpunten',
        ],
        'triage_care_question' => [
            'agent' => 'liv', 'level' => 'protocol', 'approver' => null,
            'name' => 'Klantvraag triëren',
            'input' => 'een klantvraag uit het portaal',
            'output' => 'antwoord uit de kennisbank, of directe escalatie',
        ],
        'prepare_project_case' => [
            'agent' => 'nova', 'level' => 'voorbereiden', 'approver' => 'Eigenaar van de content',
            'name' => 'Projectcase voorbereiden',
            'input' => 'getekende oplevering, vrijgegeven fotos en toestemming',
            'output' => 'conceptcase voor de website',
        ],
    ];

    /**
     * De standaardprocedures: welke gebeurtenis welke skills in gang zet. Zo zie
     * je in één oogopslag wat er na een ondertekening gebeurt.
     *
     * @var array<string, array{label: string, skills: list<string>}>
     */
    public const SOPS = [
        'lead.created' => ['label' => 'Nieuwe aanvraag binnen', 'skills' => ['complete_intake']],
        'intake.confirmed' => ['label' => 'Intake bevestigd', 'skills' => ['propose_calculation_lines']],
        'calculation.confirmed' => ['label' => 'Calculatie bevestigd', 'skills' => ['create_quote_draft']],
        'quote.signed' => ['label' => 'Offerte getekend', 'skills' => ['create_project_from_quote', 'prepare_customer_update']],
        'work_package.ready' => ['label' => 'Werkpakket gereedgemeld', 'skills' => ['check_work_package_readiness']],
        'evidence.uploaded' => ['label' => 'Foto-bewijs geüpload', 'skills' => ['classify_evidence']],
        'variation.signalled' => ['label' => 'Meerwerk gesignaleerd', 'skills' => ['propose_variation']],
        'delivery.reached' => ['label' => 'Oplevering bereikt', 'skills' => ['prepare_handover_report']],
        'care.question' => ['label' => 'Klantvraag uit het portaal', 'skills' => ['triage_care_question']],
    ];

    /**
     * @return array{name: string, field: string, owner: string, task: string, colour: string}
     */
    public static function agent(string $id): array
    {
        return self::AGENTS[$id] ?? self::AGENTS['nova'];
    }

    /**
     * @return array{agent: string, name: string, level: string, approver: ?string, input: string, output: string}|null
     */
    public static function skill(string $id): ?array
    {
        return self::SKILLS[$id] ?? null;
    }

    /**
     * De skills van een agent.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function skillsOf(string $agentId): array
    {
        return array_filter(self::SKILLS, fn (array $skill) => $skill['agent'] === $agentId);
    }

    /** Bij dit niveau en hoger beslist altijd een mens voordat er iets gebeurt. */
    public static function needsApproval(string $level): bool
    {
        return in_array($level, ['voorbereiden', 'begrensd'], true);
    }

    public static function chipClasses(string $colour): string
    {
        return match ($colour) {
            'brand' => 'bg-brand-500/15 text-brand-300',
            'lead' => 'bg-lead-500/15 text-lead-300',
            'calc' => 'bg-calc-500/15 text-calc-300',
            'klant' => 'bg-klant-500/15 text-klant-300',
            'project' => 'bg-project-500/15 text-project-300',
            'planning' => 'bg-planning-500/15 text-planning-300',
            'amber' => 'bg-amber-500/15 text-amber-300',
            'green' => 'bg-green-500/15 text-green-300',
            default => 'bg-white/10 text-slate-300',
        };
    }
}
