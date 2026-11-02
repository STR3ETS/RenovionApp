<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Invoice;
use App\Models\JobReport;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ServiceTicket;
use App\Models\SnagItem;
use App\Models\WorkOrder;

/**
 * Rapporten uit de data die er al is. Geen losse rapportagetabel: een rapport is
 * een momentopname die je op elk moment opnieuw kunt maken.
 *
 * Elk rapport bestaat uit kerncijfers en een paar secties met regels. Een regel
 * die niet goed is, wordt als zodanig gemarkeerd, zodat je in een weekrapport
 * niet hoeft te zoeken wat er aandacht vraagt.
 */
class ReportBuilder
{
    /** @var array<string, array{label: string, about: string, icon: string}> */
    public const TYPES = [
        'week' => [
            'label' => 'Weekrapport',
            'about' => 'Omzet, uren, werkbonnen en risicos van deze week',
            'icon' => 'calendar',
        ],
        'project' => [
            'label' => 'Projectrapport',
            'about' => 'Status, financiën, uren en kwaliteit per dossier',
            'icon' => 'folder',
        ],
        'sales' => [
            'label' => 'Salesrapport',
            'about' => 'Pipeline, fasen en opvolging',
            'icon' => 'funnel',
        ],
        'finance' => [
            'label' => 'Financieel maandrapport',
            'about' => 'Omzet, debiteuren, crediteuren en cashflow',
            'icon' => 'banknotes',
        ],
        'quality' => [
            'label' => 'Kwaliteitsrapport',
            'about' => 'Restpunten, meldingen vanaf de bouwplaats en service',
            'icon' => 'shield-check',
        ],
    ];

    /**
     * @return array{type: string, title: string, period: string, kpis: list<array{label: string, value: string, bad: bool}>, sections: list<array{title: string, lines: list<array{text: string, bad: bool}>}>}
     */
    public function build(string $type, ?Project $project = null): array
    {
        return match ($type) {
            'project' => $this->project($project),
            'sales' => $this->sales(),
            'finance' => $this->finance(),
            'quality' => $this->quality(),
            default => $this->week(),
        };
    }

    /**
     * Het weekrapport: wat er deze week gebeurde en wat er niet goed loopt.
     */
    private function week(): array
    {
        $from = today()->startOfWeek();
        $until = today()->endOfWeek();

        $orders = WorkOrder::approved()->whereBetween('date', [$from, $until])->with(['user', 'project'])->get();
        $invoiced = Invoice::revenue()->whereBetween('date', [$from, $until])->get();
        $pending = WorkOrder::awaitingApproval()->with(['user', 'project'])->get();
        $behind = Project::active()->whereDate('end_date_expected', '<', today())->with('customer')->get();
        $reports = JobReport::open()->with(['project', 'reporter'])->get();

        return [
            'type' => 'week',
            'title' => 'Weekrapport week '.$from->isoWeek(),
            'period' => $from->translatedFormat('j M').' tot '.$until->translatedFormat('j M Y'),
            'kpis' => [
                $this->kpi('Gefactureerd', 'EUR '.number_format((float) $invoiced->sum('amount'), 0, ',', '.')),
                $this->kpi('Goedgekeurde uren', $this->hours((float) $orders->sum('hours'))),
                $this->kpi('Werkbonnen ter controle', (string) $pending->count(), $pending->count() > 0),
                $this->kpi('Projecten over de datum', (string) $behind->count(), $behind->count() > 0),
                $this->kpi('Open meldingen bouwplaats', (string) $reports->count(), $reports->count() > 0),
            ],
            'sections' => [
                [
                    'title' => 'Geboekte uren per medewerker',
                    'lines' => $orders->groupBy('user_id')->map(fn ($set) => $this->line(sprintf(
                        '%s: %s over %d %s',
                        $set->first()->user?->name ?? 'onbekend',
                        $this->hours((float) $set->sum('hours')),
                        $set->count(),
                        $set->count() === 1 ? 'werkbon' : 'werkbonnen',
                    )))->values()->all(),
                ],
                [
                    'title' => 'Wacht op goedkeuring',
                    'lines' => $pending->map(fn (WorkOrder $order) => $this->line(sprintf(
                        '%s: %s, %s',
                        $order->number,
                        $order->project->name,
                        $this->hours((float) $order->hours),
                    ), true))->all(),
                ],
                [
                    'title' => 'Projecten over de opleverdatum',
                    'lines' => $behind->map(fn (Project $p) => $this->line(sprintf(
                        '%s: verwachte oplevering was %s, voortgang %d procent',
                        $p->name,
                        $p->end_date_expected->translatedFormat('j M'),
                        $p->progress,
                    ), true))->all(),
                ],
                [
                    'title' => 'Open meldingen vanaf de bouwplaats',
                    'lines' => $reports->map(fn (JobReport $r) => $this->line(sprintf(
                        '%s: %s (%s)',
                        $r->type->label(),
                        str($r->body)->limit(80)->value(),
                        $r->project?->name ?? 'geen project',
                    ), $r->urgent))->all(),
                ],
            ],
        ];
    }

    private function project(?Project $project): array
    {
        if ($project === null) {
            return [
                'type' => 'project',
                'title' => 'Projectrapport',
                'period' => '',
                'kpis' => [],
                'sections' => [['title' => 'Geen project gekozen', 'lines' => []]],
            ];
        }

        $project->load(['customer', 'snags', 'workOrders', 'invoices', 'qualityReview']);
        $review = app(CostReviewService::class)->for($project);
        $orders = $project->workOrders->filter(fn (WorkOrder $o) => $o->status === WorkOrderStatus::Goedgekeurd);
        $open = $project->snags->filter->isOpen();

        return [
            'type' => 'project',
            'title' => 'Projectrapport '.$project->name,
            'period' => collect([
                $project->start_date?->translatedFormat('j M Y'),
                $project->end_date_expected?->translatedFormat('j M Y'),
            ])->filter()->join(' tot '),
            'kpis' => [
                $this->kpi('Voortgang', $project->progress.'%'),
                $this->kpi('Aanneemsom', 'EUR '.number_format((float) $project->value, 0, ',', '.')),
                $this->kpi('Verwachte kosten', $review === null ? 'geen begroting' : 'EUR '.number_format($review['expected'], 0, ',', '.')),
                $this->kpi(
                    'Marge verwacht',
                    $review === null ? 'onbekend' : number_format($review['margin_expected'], 1, ',', '.').'%',
                    $review !== null && $review['margin_expected'] < $review['margin_budgeted'] - 2,
                ),
                $this->kpi('Open restpunten', (string) $open->count(), $open->count() > 0),
            ],
            'sections' => [
                [
                    'title' => 'Financieel',
                    'lines' => [
                        $this->line('Gefactureerd: EUR '.number_format((float) $project->invoices->sum('amount'), 0, ',', '.')),
                        $this->line('Goedgekeurde uren: '.$this->hours((float) $orders->sum('hours'))),
                        $review === null
                            ? $this->line('Geen begroting om tegen af te zetten', true)
                            : $this->line(sprintf(
                                'Afwijking tegenover begroting: %s%s procent',
                                $review['variance'] > 0 ? '+' : '',
                                number_format($review['variance'], 1, ',', '.'),
                            ), $review['variance'] > 5),
                    ],
                ],
                [
                    'title' => 'Kwaliteit',
                    'lines' => $open->isEmpty()
                        ? [$this->line('Geen open restpunten')]
                        : $open->map(fn (SnagItem $s) => $this->line(sprintf(
                            '%s (%s), %s',
                            $s->title,
                            $s->location ?: 'geen locatie',
                            $s->ownerName(),
                        ), true))->values()->all(),
                ],
            ],
        ];
    }

    private function sales(): array
    {
        $leads = Lead::with('customer')->get();
        $open = $leads->filter(fn (Lead $l) => $l->status->isOpen());
        $won = $leads->where('status', LeadStatus::Project);
        $lost = $leads->where('status', LeadStatus::Verloren);
        $silent = $open->filter(fn (Lead $l) => $l->next_action_at === null);

        return [
            'type' => 'sales',
            'title' => 'Salesrapport',
            'period' => 'stand op '.today()->translatedFormat('j F Y'),
            'kpis' => [
                $this->kpi('Open aanvragen', (string) $open->count()),
                $this->kpi('Zonder vervolgstap', (string) $silent->count(), $silent->count() > 0),
                $this->kpi('Gewonnen', (string) $won->count()),
                $this->kpi('Verloren', (string) $lost->count()),
                $this->kpi(
                    'Hitrate',
                    ($won->count() + $lost->count()) > 0
                        ? number_format($won->count() / ($won->count() + $lost->count()) * 100, 1, ',', '.').'%'
                        : 'geen meting',
                ),
            ],
            'sections' => [
                [
                    'title' => 'Per fase',
                    'lines' => $open->groupBy(fn (Lead $l) => $l->status->label())
                        ->map(fn ($set, $label) => $this->line(sprintf(
                            '%s: %d, samen EUR %s',
                            $label,
                            $set->count(),
                            number_format((float) $set->sum('value'), 0, ',', '.'),
                        )))->values()->all(),
                ],
                [
                    'title' => 'Zonder vervolgstap',
                    'lines' => $silent->map(fn (Lead $l) => $this->line(sprintf(
                        '%s: %s, laatste contact %s',
                        $l->customer?->name ?? 'onbekend',
                        $l->service ?: 'geen dienst',
                        $l->last_contact_at?->diffForHumans() ?? 'nooit',
                    ), true))->values()->all(),
                ],
                [
                    'title' => 'Verloren met reden',
                    'lines' => $lost->groupBy(fn (Lead $l) => $l->lost_reason ?: 'geen reden vastgelegd')
                        ->map(fn ($set, $reason) => $this->line("{$reason}: {$set->count()}"))
                        ->values()->all(),
                ],
            ],
        ];
    }

    private function finance(): array
    {
        $finance = app(FinanceOverview::class)->build();

        return [
            'type' => 'finance',
            'title' => 'Financieel maandrapport '.now()->translatedFormat('F Y'),
            'period' => 'stand op '.today()->translatedFormat('j F Y'),
            'kpis' => [
                $this->kpi('Omzet dit jaar', 'EUR '.number_format($finance['revenue_year'], 0, ',', '.')),
                $this->kpi('Werkvoorraad', 'EUR '.number_format($finance['order_book'], 0, ',', '.')),
                $this->kpi('Debiteuren', 'EUR '.number_format($finance['debtors'], 0, ',', '.')),
                $this->kpi('Te laat', 'EUR '.number_format($finance['debtors_overdue'], 0, ',', '.'), $finance['debtors_overdue'] > 0),
                $this->kpi('Crediteuren', 'EUR '.number_format($finance['creditors'], 0, ',', '.')),
            ],
            'sections' => [
                [
                    'title' => 'Cashflowprognose',
                    'lines' => collect($finance['cashflow'])->map(fn (array $m) => $this->line(sprintf(
                        '%s: binnen EUR %s, uit EUR %s, saldo EUR %s',
                        $m['label'],
                        number_format($m['in'], 0, ',', '.'),
                        number_format($m['out'], 0, ',', '.'),
                        number_format($m['net'], 0, ',', '.'),
                    ), $m['net'] < 0))->all(),
                ],
                [
                    'title' => 'Facturen over de vervaldatum',
                    'lines' => $finance['debtor_invoices']->filter->isOverdue()
                        ->map(fn (Invoice $i) => $this->line(sprintf(
                            '%s: %s, EUR %s, %d dagen te laat',
                            $i->number,
                            $i->customer->name,
                            number_format($i->openAmount(), 0, ',', '.'),
                            $i->daysOverdue(),
                        ), true))->values()->all(),
                ],
            ],
        ];
    }

    private function quality(): array
    {
        $snags = SnagItem::open()->with(['project', 'assignee', 'subcontractor'])->get();
        $overdue = $snags->filter->isOverdue();
        $tickets = ServiceTicket::open()->with(['project', 'customer'])->get();
        $reports = JobReport::open()->with(['project', 'reporter'])->get();

        return [
            'type' => 'quality',
            'title' => 'Kwaliteitsrapport',
            'period' => 'stand op '.today()->translatedFormat('j F Y'),
            'kpis' => [
                $this->kpi('Open restpunten', (string) $snags->count(), $snags->count() > 0),
                $this->kpi('Over de hersteldatum', (string) $overdue->count(), $overdue->count() > 0),
                $this->kpi('Open servicemeldingen', (string) $tickets->count(), $tickets->count() > 0),
                $this->kpi('Waarvan buiten garantie', (string) $tickets->where('warranty', false)->count()),
                $this->kpi('Meldingen bouwplaats', (string) $reports->count(), $reports->count() > 0),
            ],
            'sections' => [
                [
                    'title' => 'Restpunten per project',
                    'lines' => $snags->groupBy('project_id')->map(fn ($set) => $this->line(sprintf(
                        '%s: %d open, %d over de datum',
                        $set->first()->project->name,
                        $set->count(),
                        $set->filter->isOverdue()->count(),
                    ), $set->filter->isOverdue()->isNotEmpty()))->values()->all(),
                ],
                [
                    'title' => 'Servicemeldingen',
                    'lines' => $tickets->map(fn (ServiceTicket $t) => $this->line(sprintf(
                        '%s: %s, %s, %d dagen open',
                        $t->number,
                        $t->title,
                        $t->warranty ? 'onder garantie' : 'buiten garantie',
                        $t->daysOpen(),
                    ), $t->priority === 'spoed' || $t->daysOpen() > 7))->all(),
                ],
            ],
        ];
    }

    /**
     * @return array{label: string, value: string, bad: bool}
     */
    private function kpi(string $label, string $value, bool $bad = false): array
    {
        return ['label' => $label, 'value' => $value, 'bad' => $bad];
    }

    /**
     * @return array{text: string, bad: bool}
     */
    private function line(string $text, bool $bad = false): array
    {
        return ['text' => $text, 'bad' => $bad];
    }

    private function hours(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',').' uur';
    }
}
