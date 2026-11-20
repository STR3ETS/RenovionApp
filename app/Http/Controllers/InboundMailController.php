<?php

namespace App\Http\Controllers;

use App\Enums\ActionSource;
use App\Enums\CustomerType;
use App\Enums\LeadStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TimelineEventType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\InboundMail;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Services\AttentionService;
use App\Services\InboundMailReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

/**
 * De aanvraagmail: wat er binnenkomt op aanvraag@ en via de persoonlijke
 * opdrachtlink van zakelijke klanten. Nova leest wat er gevraagd wordt, een
 * mens bevestigt en koppelt het aan een klant, en zet het daarna uit als
 * aanvraag.
 */
class InboundMailController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();

        $mails = InboundMail::query()
            ->when($status === '', fn ($query) => $query->open())
            ->when(array_key_exists($status, InboundMail::STATUSES), fn ($query) => $query->where('status', $status))
            ->with(['customer', 'lead', 'handler'])
            ->latest('received_at')
            ->latest('id')
            ->limit(100)
            ->get();

        $selected = $request->integer('bericht')
            ? InboundMail::with(['customer.frameworkAgreements', 'lead'])->find($request->integer('bericht'))
            : $mails->first()?->load('customer.frameworkAgreements');

        return view('inbound-mails.index', [
            'mails' => $mails,
            'selected' => $selected,
            'status' => $status,
            'counts' => InboundMail::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'number', 'type']),
        ]);
    }

    /** Een mail met de hand invoeren, zolang de mailbox nog niet gekoppeld is. */
    public function store(Request $request, InboundMailReader $reader): RedirectResponse
    {
        $validated = $request->validate([
            'from_name' => ['nullable', 'string', 'max:255'],
            'from_email' => ['nullable', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $mail = InboundMail::create([...$validated, 'source' => InboundMail::SOURCE_MAIL, 'received_at' => now()]);
        $mail->update(['extracted' => $reader->read($mail)]);

        AttentionService::forgetCount();

        return redirect()->route('inbound-mails.index', ['bericht' => $mail->id])
            ->with('success', 'Bericht toegevoegd. Controleer wat Nova eruit las en koppel het aan een klant.');
    }

    /** Nova opnieuw laten lezen, bijvoorbeeld nadat de koppeling met het model is gelegd. */
    public function reread(InboundMail $mail, InboundMailReader $reader): RedirectResponse
    {
        if ($mail->status === InboundMail::STATUS_DISPATCHED) {
            return back()->with('error', 'Dit bericht is al uitgezet.');
        }

        $mail->update(['extracted' => [...$reader->read($mail), 'nieuwe_klant' => $mail->extracted['nieuwe_klant'] ?? false]]);

        return back()->with('success', 'Opnieuw gelezen.');
    }

    /**
     * Koppelen: de mens bevestigt bij welke klant dit hoort en wat er gevraagd
     * wordt. Bestaat de klant nog niet, dan maken we hem hier aan.
     */
    public function link(Request $request, InboundMail $mail): RedirectResponse
    {
        if ($mail->status === InboundMail::STATUS_DISPATCHED) {
            return back()->with('error', 'Dit bericht is al uitgezet.');
        }

        $validated = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id', 'required_without:new_name'],
            'new_name' => ['nullable', 'string', 'max:255', 'required_without:customer_id'],
            'new_type' => ['nullable', new Enum(CustomerType::class)],
            'werk' => ['required', 'string', 'max:255'],
            'adres' => ['nullable', 'string', 'max:255'],
            'deadline' => ['nullable', 'date'],
        ], [
            'customer_id.required_without' => 'Kies een klant, of vul de naam van een nieuwe klant in.',
            'new_name.required_without' => 'Kies een klant, of vul de naam van een nieuwe klant in.',
        ]);

        DB::transaction(function () use ($mail, $validated, $request) {
            $isNew = blank($validated['customer_id'] ?? null);

            if ($isNew) {
                $type = CustomerType::from($validated['new_type'] ?? CustomerType::Particulier->value);

                $customer = Customer::create([
                    'name' => $validated['new_name'],
                    'type' => $type,
                    'email' => $mail->from_email,
                    'contact_name' => $type === CustomerType::Zakelijk ? $mail->from_name : null,
                    'payment_days' => $type->defaultPaymentDays(),
                ]);
            } else {
                $customer = Customer::findOrFail($validated['customer_id']);
            }

            $mail->update([
                'customer_id' => $customer->id,
                'status' => InboundMail::STATUS_LINKED,
                'handled_by' => $request->user()->id,
                'extracted' => [
                    ...($mail->extracted ?? []),
                    'werk' => $validated['werk'],
                    'adres' => $validated['adres'] ?? null,
                    'deadline' => $validated['deadline'] ?? null,
                    'klant_id' => $customer->id,
                    'nieuwe_klant' => $isNew,
                    'bevestigd_door' => $request->user()->name,
                ],
            ]);

            AuditLog::record($mail, 'aanvraagmail gekoppeld', [], ['klant' => $customer->name, 'nieuwe_klant' => $isNew]);
        });

        return redirect()->route('inbound-mails.index', ['bericht' => $mail->id])
            ->with('success', 'Gekoppeld aan '.$mail->fresh()->customer->name.'. Zet het bericht nu uit als aanvraag.');
    }

    /**
     * Uitzetten: het bericht wordt een aanvraag. Voor een bestaande klant
     * slaan we de eerste fase over: het dossier staat direct klaar om te
     * calculeren, met een taak "Offerte maken". Een nieuwe klant begint
     * gewoon bij de aanvraag.
     */
    public function dispatchMail(Request $request, InboundMail $mail): RedirectResponse
    {
        if ($mail->status !== InboundMail::STATUS_LINKED || $mail->customer === null) {
            return back()->with('error', $mail->status === InboundMail::STATUS_DISPATCHED
                ? 'Dit bericht is al uitgezet.'
                : 'Koppel het bericht eerst aan een klant.');
        }

        $existing = $mail->isForExistingCustomer();
        $calculator = $this->calculator($request->user());

        $lead = DB::transaction(function () use ($mail, $existing, $calculator, $request) {
            $customer = $mail->customer;

            // Het adres van het werk hoort in het dossier als we er nog geen hebben.
            if (blank($customer->address) && $mail->read('adres')) {
                $customer->update(['address' => $mail->read('adres')]);
            }

            $lead = Lead::create([
                'customer_id' => $customer->id,
                'service' => $mail->read('werk'),
                'description' => collect([
                    $mail->read('adres') ? 'Adres: '.$mail->read('adres') : null,
                    $mail->read('deadline') ? 'Deadline: '.$mail->read('deadline') : null,
                    $mail->body,
                ])->filter()->join("\n\n"),
                'source' => $mail->fromPortal() ? ActionSource::Website : ActionSource::Handmatig,
                'campaign' => $mail->fromPortal() ? 'Opdrachtlink' : 'Aanvraagmail',
                'status' => $existing ? LeadStatus::Offerte : LeadStatus::Nieuw,
                'desired_start' => $mail->read('deadline'),
                'assigned_to' => $existing ? $calculator->id : $request->user()->id,
                'last_contact_at' => $mail->received_at,
            ]);

            $customer->recordEvent(
                TimelineEventType::Aanvraag,
                ($mail->fromPortal() ? 'Opdracht via de persoonlijke link: ' : 'Aanvraag per mail: ').$mail->read('werk'),
                $mail->body,
                $lead,
                $mail->fromPortal() ? ActionSource::Website : ActionSource::Handmatig,
            );

            if ($existing) {
                Task::create([
                    'title' => 'Offerte maken: '.$mail->read('werk'),
                    'note' => collect([$customer->name, $mail->read('adres'), $mail->read('deadline') ? 'deadline '.$mail->read('deadline') : null])->filter()->join(', '),
                    'customer_id' => $customer->id,
                    'lead_id' => $lead->id,
                    'owner_id' => $calculator->id,
                    'deadline' => today()->addWeekdays(2),
                    'status' => TaskStatus::TeDoen,
                    'priority' => TaskPriority::Hoog,
                    'source' => ActionSource::Nova,
                ]);
            }

            $mail->update(['status' => InboundMail::STATUS_DISPATCHED, 'lead_id' => $lead->id, 'handled_by' => $request->user()->id]);

            AuditLog::record($mail, 'aanvraagmail uitgezet', [], ['aanvraag' => $lead->id, 'bestaande_klant' => $existing]);

            return $lead;
        });

        AttentionService::forgetCount();

        return redirect()->route('leads.show', $lead)->with('success', $existing
            ? "Uitgezet. Bestaande klant, dus het dossier staat direct klaar om te calculeren. {$calculator->name} heeft de taak Offerte maken."
            : 'Uitgezet als nieuwe aanvraag.');
    }

    /** Wie de offerte maakt: een calculator als die er is, anders wie het bericht uitzet. */
    private function calculator(User $fallback): User
    {
        return User::where('role', UserRole::Calculator)->where('active', true)->orderBy('id')->first() ?? $fallback;
    }
}
