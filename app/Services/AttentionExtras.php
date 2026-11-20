<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Certificate;
use App\Models\ExtraWork;
use App\Models\FrameworkAgreement;
use App\Models\InboundMail;
use App\Models\Invoice;
use App\Models\ProjectAgreement;
use App\Models\PurchaseOrder;
use App\Models\SubcontractorDocument;
use App\Models\SubcontractorFramework;
use App\Support\Margin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * De signalen uit ronde 28 tot en met 34 voor de inbox: de aanvraagmail, wat
 * binnenkort verloopt, facturen over de vervaldatum, meerwerk dat wacht en het
 * 75-procentmoment van werk dat we uitbesteden.
 */
class AttentionExtras
{
    /** Zoveel dagen voor het verlopen begint het signaal. */
    public const EXPIRY_DAYS = 31;

    /** Vanaf deze voortgang kijken we of de marge op uitbesteed werk nog klopt. */
    public const MARGIN_CHECK_PROGRESS = 75;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function items(): Collection
    {
        return collect()
            ->concat($this->inboundMails())
            ->concat($this->overdueInvoices())
            ->concat($this->expiringPapers())
            ->concat($this->extraWork())
            ->concat($this->marginMoments())
            ->concat($this->openAgreements());
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function inboundMails(): Collection
    {
        return InboundMail::open()
            ->with('customer')
            ->latest('received_at')
            ->get()
            ->map(fn (InboundMail $mail) => [
                'severity' => $mail->received_at?->lt(now()->subDay()) ? 'rood' : 'oranje',
                'icon' => 'envelope',
                'label' => $mail->status === InboundMail::STATUS_LINKED ? 'Aanvraagmail uitzetten' : 'Nieuwe aanvraagmail',
                'title' => $mail->read('werk') ?? $mail->subject ?? 'Bericht zonder onderwerp',
                'subtitle' => collect([
                    $mail->customer?->name ?? $mail->from_name ?? $mail->from_email,
                    $mail->fromPortal() ? 'via de opdrachtlink' : null,
                    $mail->received_at?->diffForHumans(),
                ])->filter()->join(', '),
                'url' => route('inbound-mails.index', ['bericht' => $mail->id]),
            ]);
    }

    /**
     * Facturen over de vervaldatum. Of we een herinnering sturen hangt af van
     * de opdrachtgever: wie dat uit heeft staan, bellen we zelf.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function overdueInvoices(): Collection
    {
        return Invoice::query()
            ->where('status', InvoiceStatus::Verzonden)
            ->whereDate('due_date', '<', today())
            ->with('customer')
            ->get()
            ->map(fn (Invoice $invoice) => [
                'severity' => $invoice->daysOverdue() >= 14 ? 'rood' : 'oranje',
                'icon' => 'currency-euro',
                'label' => 'Factuur te laat',
                'title' => $invoice->customer->name.', EUR '.number_format($invoice->openAmount(), 0, ',', '.'),
                'subtitle' => $invoice->number.', '.$invoice->daysOverdue().' dagen over de vervaldatum, '
                    .($invoice->customer->reminders ? 'herinnering mag' : 'klant wil geen herinnering, bel zelf'),
                'url' => route('invoices.show', $invoice),
            ]);
    }

    /**
     * Certificaten, dossierstukken en overeenkomsten die binnen 31 dagen
     * verlopen of al verlopen zijn.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function expiringPapers(): Collection
    {
        $limit = today()->addDays(self::EXPIRY_DAYS);

        $certificates = Certificate::query()
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<=', $limit)
            ->with(['user', 'subcontractor'])
            ->get()
            ->filter(fn (Certificate $certificate) => $certificate->user?->active ?? $certificate->subcontractor !== null)
            ->map(fn (Certificate $certificate) => $this->expiry(
                $certificate->valid_until,
                'Certificaat',
                ($certificate->meta()['short'] ?? $certificate->type).' van '.($certificate->user?->name ?? $certificate->subcontractor?->name),
                $certificate->subcontractor_id !== null
                    ? route('subcontractors.show', $certificate->subcontractor_id)
                    : route('workforce.index'),
            ));

        $documents = SubcontractorDocument::query()
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<=', $limit)
            ->with('subcontractor')
            ->get()
            ->filter(fn (SubcontractorDocument $document) => $document->subcontractor !== null && ! $document->subcontractor->blocked)
            ->map(fn (SubcontractorDocument $document) => $this->expiry(
                $document->valid_until,
                'Dossierstuk',
                strtoupper($document->type).' van '.$document->subcontractor->name,
                route('subcontractors.show', $document->subcontractor_id),
            ));

        // Alleen de laatste periode per partij telt: een verlengde overeenkomst verloopt niet.
        $partnerFrameworks = SubcontractorFramework::query()
            ->with('subcontractor')
            ->get()
            ->groupBy('subcontractor_id')
            ->map(fn (Collection $frameworks) => $frameworks->sortByDesc('ends_on')->first())
            ->filter(fn (SubcontractorFramework $framework) => $framework->ends_on->lte($limit) && $framework->subcontractor !== null)
            ->map(fn (SubcontractorFramework $framework) => $this->expiry(
                $framework->ends_on,
                'Raamovereenkomst onderaannemer',
                $framework->subcontractor->name.', leg een nieuwe periode vast',
                route('subcontractors.show', $framework->subcontractor_id),
            ));

        $customerFrameworks = FrameworkAgreement::query()
            ->whereNotNull('ends_on')
            ->with('customer')
            ->get()
            ->groupBy('customer_id')
            ->map(fn (Collection $agreements) => $agreements->sortByDesc('ends_on')->first())
            ->filter(fn (FrameworkAgreement $agreement) => $agreement->ends_on->lte($limit))
            ->map(fn (FrameworkAgreement $agreement) => $this->expiry(
                $agreement->ends_on,
                'Raamovereenkomst klant',
                $agreement->number.' met '.$agreement->customer->name,
                route('customers.show', $agreement->customer_id),
            ));

        return collect()
            ->concat($certificates)
            ->concat($documents)
            ->concat($partnerFrameworks)
            ->concat($customerFrameworks)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function expiry(Carbon $date, string $what, string $title, string $url): array
    {
        $days = (int) today()->diffInDays($date, false);

        return [
            'severity' => $days < 0 ? 'rood' : 'oranje',
            'icon' => 'clock',
            'label' => $days < 0 ? $what.' verlopen' : $what.' verloopt',
            'title' => $title,
            'subtitle' => $days < 0
                ? 'verlopen op '.$date->translatedFormat('j M Y')
                : 'verloopt op '.$date->translatedFormat('j M Y').', over '.$days.' '.($days === 1 ? 'dag' : 'dagen'),
            'url' => $url,
        ];
    }

    /**
     * Meerwerk dat klaar is om te factureren, en meerwerk dat al dagen bij de
     * klant ligt zonder akkoord.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function extraWork(): Collection
    {
        return ExtraWork::query()
            ->whereIn('status', [ExtraWork::STATUS_SENT, ExtraWork::STATUS_APPROVED])
            ->with('project.customer')
            ->get()
            ->filter(fn (ExtraWork $extra) => $extra->status === ExtraWork::STATUS_APPROVED
                || $extra->sent_at?->lte(now()->subDays(3)))
            ->map(fn (ExtraWork $extra) => [
                'severity' => 'oranje',
                'icon' => 'currency-euro',
                'label' => $extra->status === ExtraWork::STATUS_APPROVED ? 'Meerwerk factureren' : 'Meerwerk wacht op akkoord',
                'title' => $extra->number.', EUR '.number_format((float) $extra->amount, 0, ',', '.'),
                'subtitle' => collect([
                    $extra->description,
                    $extra->project?->customer?->name,
                    $extra->status === ExtraWork::STATUS_APPROVED
                        ? 'akkoord van '.$extra->approved_name
                        : 'bij de klant sinds '.$extra->sent_at->translatedFormat('j M'),
                ])->filter()->join(', '),
                'url' => route('projects.show', [$extra->project_id, 'tab' => 'meerwerk']),
            ])
            ->values();
    }

    /**
     * Het 75-procentmoment: is het project driekwart klaar, dan kijken we of
     * de marge op uitbesteed werk onder de norm zit, en of de inkoopfactuur
     * van de onderaannemer binnen is voordat wij zelf gefactureerd hebben.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function marginMoments(): Collection
    {
        $orders = PurchaseOrder::query()
            ->whereNotNull('rfq_id')
            ->whereHas('project', fn ($project) => $project->active()->where('progress', '>=', self::MARGIN_CHECK_PROGRESS))
            ->with(['project.invoices', 'invoices', 'rfq.bids', 'subcontractor'])
            ->get();

        $items = collect();

        foreach ($orders as $order) {
            $bid = $order->rfq?->bids->firstWhere('purchase_order_id', $order->id);
            $margin = $bid?->marginPct();

            if ($margin !== null && $margin < Margin::baseline()) {
                $items->push([
                    'severity' => $margin < Margin::floor() ? 'rood' : 'oranje',
                    'icon' => 'scale',
                    'label' => 'Marge onder de norm',
                    'title' => $order->project->name.', '.$order->supplier,
                    'subtitle' => 'project op '.$order->project->progress.'%, marge '.Margin::format($margin).' op '.$order->rfq->title,
                    'url' => route('rfqs.show', $order->rfq_id),
                ]);
            }

            $firstPurchase = $order->invoices->sortBy('date')->first();
            $firstSale = $order->project->invoices
                ->filter(fn (Invoice $invoice) => $invoice->sent_at !== null)
                ->sortBy('date')
                ->first();

            if ($firstPurchase !== null && ($firstSale === null || $firstSale->date->gt($firstPurchase->date))) {
                $items->push([
                    'severity' => 'oranje',
                    'icon' => 'currency-euro',
                    'label' => 'Inkoopfactuur voor verkoopfactuur',
                    'title' => $order->project->name.', '.$order->supplier,
                    'subtitle' => 'de factuur van de onderaannemer is binnen ('.$firstPurchase->date->translatedFormat('j M').')'
                        .($firstSale === null ? ', wij hebben nog niets gefactureerd' : ', eerder dan onze eerste factuur'),
                    'url' => route('projects.show', [$order->project_id, 'tab' => 'meerwerk']),
                ]);
            }
        }

        return $items;
    }

    /**
     * Gegund, maar de getekende overeenkomst is na drie dagen nog niet vastgelegd.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function openAgreements(): Collection
    {
        return ProjectAgreement::query()
            ->whereNull('recorded_at')
            ->where('created_at', '<=', now()->subDays(3))
            ->with(['project', 'subcontractor'])
            ->get()
            ->map(fn (ProjectAgreement $agreement) => [
                'severity' => 'oranje',
                'icon' => 'document-text',
                'label' => 'Overeenkomst nog niet vastgelegd',
                'title' => $agreement->subcontractor->name,
                'subtitle' => $agreement->project->name.', gegund op '.$agreement->created_at->translatedFormat('j M'),
                'url' => route('projects.show', [$agreement->project_id, 'tab' => 'aanmelding']),
            ]);
    }
}
