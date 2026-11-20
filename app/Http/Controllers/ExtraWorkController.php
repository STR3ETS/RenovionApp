<?php

namespace App\Http\Controllers;

use App\Enums\ActionSource;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\TimelineEventType;
use App\Models\AuditLog;
use App\Models\BudgetLine;
use App\Models\ChatChannel;
use App\Models\ExtraWork;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Meerwerk als eigen opdracht onder het projectnummer. De volgorde ligt vast:
 * opstellen, naar de klant, akkoord, en dan pas uitvoeren en factureren. De
 * factuur van het meerwerk staat los van de termijnen: de hoofdfactuur wacht
 * er nooit op.
 */
class ExtraWorkController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $request->merge(['amount' => BudgetLine::parseNumber($request->input('amount'))]);

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:5000'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:10000000'],
        ]);

        $extra = $project->extraWorks()->create([
            ...$validated,
            'number' => ExtraWork::nextNumberFor($project),
            'status' => ExtraWork::STATUS_DRAFT,
            'public_token' => ExtraWork::newToken(),
            'created_by' => $request->user()->id,
        ]);

        AuditLog::record($extra, 'meerwerk opgesteld', [], ['nummer' => $extra->number, 'bedrag' => (float) $extra->amount]);

        return back()->with('success', "Meerwerk {$extra->number} staat klaar als concept.");
    }

    /** Naar de klant: vanaf nu kan hij via de link akkoord geven. */
    public function send(ExtraWork $extraWork): RedirectResponse
    {
        if ($extraWork->status !== ExtraWork::STATUS_DRAFT) {
            return back()->with('error', 'Dit meerwerk is al naar de klant.');
        }

        $extraWork->update(['status' => ExtraWork::STATUS_SENT, 'sent_at' => now()]);

        AuditLog::record($extraWork, 'meerwerk verzonden', ['status' => ExtraWork::STATUS_DRAFT], ['status' => ExtraWork::STATUS_SENT]);

        $extraWork->project->customer?->recordEvent(
            TimelineEventType::Offerte,
            "Meerwerk {$extraWork->number} ter akkoord aangeboden",
            $extraWork->description.', € '.number_format((float) $extraWork->amount, 2, ',', '.'),
            $extraWork->project,
        );

        return back()->with('success', 'Klaar voor de klant. Kopieer de link en stuur hem door: de klant geeft daar online akkoord.');
    }

    /** Akkoord dat buiten het systeem om is gegeven, bijvoorbeeld per mail of op de bouwplaats. */
    public function approve(Request $request, ExtraWork $extraWork): RedirectResponse
    {
        $validated = $request->validate(['approved_name' => ['required', 'string', 'max:200']]);

        if ($extraWork->isApproved()) {
            return back()->with('error', 'Dit meerwerk heeft al akkoord.');
        }

        $this->markApproved($extraWork, $validated['approved_name'], null, ActionSource::Handmatig);

        return back()->with('success', "Akkoord van {$validated['approved_name']} vastgelegd. Het meerwerk mag uitgevoerd en gefactureerd worden.");
    }

    /** Apart factureren, als concept. Kan pas na akkoord van de klant. */
    public function invoice(ExtraWork $extraWork): RedirectResponse
    {
        if ($extraWork->status === ExtraWork::STATUS_INVOICED) {
            return back()->with('error', 'Dit meerwerk is al gefactureerd.');
        }

        if (! $extraWork->isApproved()) {
            return back()->with('error', 'Meerwerk factureren kan pas na akkoord van de klant.');
        }

        $project = $extraWork->project->load('customer');

        $invoice = DB::transaction(function () use ($extraWork, $project) {
            $invoice = Invoice::create([
                'number' => Invoice::nextNumber(),
                'project_id' => $project->id,
                'customer_id' => $project->customer_id,
                'type' => InvoiceType::Meerwerk,
                'description' => 'Meerwerk '.$extraWork->number.': '.$extraWork->description,
                'date' => today(),
                'due_date' => Invoice::dueDateFor($project->customer),
                'amount' => $extraWork->amount,
                'vat_pct' => Setting::number('calculatie.btw'),
                'status' => InvoiceStatus::Concept,
            ]);

            $invoice->lines()->create([
                'description' => $extraWork->description.' (akkoord '.$extraWork->approved_name.', '.$extraWork->approved_at->translatedFormat('j F Y').')',
                'quantity' => 1,
                'unit' => 'pst',
                'unit_price' => $extraWork->amount,
            ]);

            $extraWork->update(['status' => ExtraWork::STATUS_INVOICED, 'invoice_id' => $invoice->id]);

            return $invoice;
        });

        AuditLog::record($invoice, 'meerwerkfactuur aangemaakt', [], ['meerwerk' => $extraWork->number, 'bedrag' => (float) $invoice->amount]);

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', "Meerwerkfactuur {$invoice->number} staat klaar als concept.");
    }

    public function destroy(ExtraWork $extraWork): RedirectResponse
    {
        if ($extraWork->status !== ExtraWork::STATUS_DRAFT) {
            return back()->with('error', 'Alleen een concept kan weg. Dit meerwerk is al naar de klant.');
        }

        $extraWork->delete();

        return back()->with('success', 'Concept verwijderd.');
    }

    /** Wat de klant ziet: het meerwerk en de knop om akkoord te geven. */
    public function show(string $token): View
    {
        $extraWork = $this->publicExtra($token);

        return view('extra-works.public', [
            'extra' => $extraWork,
            'project' => $extraWork->project,
            'vatPct' => Setting::number('calculatie.btw'),
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $extraWork = $this->publicExtra($token);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'agree' => ['accepted'],
        ], [
            'agree.accepted' => 'Vink aan dat u akkoord gaat.',
        ]);

        if ($extraWork->isApproved()) {
            return back()->with('error', 'U heeft al akkoord gegeven op dit meerwerk.');
        }

        $this->markApproved($extraWork, $validated['name'], $request->ip(), ActionSource::Website);

        ChatChannel::firstOrCreate(['type' => 'team', 'name' => 'Algemeen'])->messages()->create([
            'user_id' => null,
            'body' => "Meerwerk {$extraWork->number} heeft akkoord van {$validated['name']}: {$extraWork->description}.",
            'nova' => ['executed' => true, 'url' => route('projects.show', [$extraWork->project_id, 'tab' => 'meerwerk'])],
        ]);

        return back()->with('success', 'Dank u. Uw akkoord is vastgelegd.');
    }

    /** Een concept is nog niet voor de klant: de link werkt pas na verzenden. */
    private function publicExtra(string $token): ExtraWork
    {
        $extraWork = ExtraWork::where('public_token', $token)->with('project.customer')->firstOrFail();

        abort_if($extraWork->status === ExtraWork::STATUS_DRAFT, 404);

        return $extraWork;
    }

    private function markApproved(ExtraWork $extraWork, string $name, ?string $ip, ActionSource $source): void
    {
        $extraWork->update([
            'status' => ExtraWork::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_name' => $name,
            'approved_ip' => $ip,
        ]);

        AuditLog::record($extraWork, 'meerwerk akkoord', ['status' => ExtraWork::STATUS_SENT], [
            'status' => ExtraWork::STATUS_APPROVED,
            'naam' => $name,
            'ip' => $ip,
        ], $source);

        $extraWork->project->customer?->recordEvent(
            TimelineEventType::Offerte,
            "Akkoord op meerwerk {$extraWork->number}",
            $name.', € '.number_format((float) $extraWork->amount, 2, ',', '.'),
            $extraWork->project,
            $source,
        );
    }
}
