<?php

namespace App\Http\Controllers;

use App\Enums\CustomerType;
use App\Enums\TimelineEventType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\PriceItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->trim()->toString();

        $type = CustomerType::tryFrom($request->string('soort')->toString());

        $customers = Customer::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('number', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->with('frameworkAgreements')
            ->withCount(['leads', 'projects'])
            ->withSum('projects', 'value')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'search' => $search,
            'type' => $type,
            'counts' => Customer::selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type'),
        ]);
    }

    /**
     * De gegevens waarmee we voor deze klant rekenen: soort, betaaltermijn en
     * of hij een herinnering wil. Wisselt de soort, dan gaat de termijn mee naar
     * de standaard van die soort, tenzij je er zelf een opgeeft.
     */
    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', new Enum(CustomerType::class)],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'city' => ['nullable', 'string', 'max:255'],
            'coc_number' => ['nullable', 'string', 'max:20'],
            'vat_number' => ['nullable', 'string', 'max:30'],
            'payment_days' => ['nullable', 'integer', 'min:0', 'max:120'],
            'relation' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['reminders'] = $request->boolean('reminders');

        if (blank($validated['payment_days'] ?? null)) {
            $validated['payment_days'] = CustomerType::from($validated['type'] ?? $customer->type->value)->defaultPaymentDays();
        }

        $old = $customer->only(['type', 'payment_days', 'reminders']);
        $customer->update($validated);

        AuditLog::record($customer, 'klantgegevens bijgewerkt', $old, $customer->only(['type', 'payment_days', 'reminders']));

        return back()->with('success', 'Klantgegevens bijgewerkt.');
    }

    /** De persoonlijke link waarmee een zakelijke klant een opdracht inschiet. */
    public function portalLink(Request $request, Customer $customer): RedirectResponse
    {
        if (! $customer->isBusiness()) {
            return back()->with('error', 'Een persoonlijke opdrachtlink is er alleen voor zakelijke klanten.');
        }

        // Vernieuwen maakt de oude link ongeldig, bijvoorbeeld als hij is uitgelekt.
        if ($request->boolean('vernieuwen')) {
            $customer->forceFill(['portal_token' => null])->save();
            AuditLog::record($customer, 'opdrachtlink vernieuwd');
        }

        $customer->ensurePortalToken();

        return back()->with('success', 'De opdrachtlink staat klaar. Kopieer hem hieronder en stuur hem naar de klant.');
    }

    public function show(Customer $customer): View
    {
        $customer->load([
            'frameworkAgreements.prices.priceItem',
            'frameworkAgreements.terms',
            'leads' => fn ($query) => $query->latest(),
            'quotes' => fn ($query) => $query->latest(),
            'projects' => fn ($query) => $query->latest(),
            'documents.uploader',
            'timelineEvents.user',
            'portalUser',
        ]);

        return view('customers.show', [
            'customer' => $customer,
            'priceItems' => PriceItem::where('active', true)->orderBy('code')->get(['id', 'code', 'name', 'unit', 'unit_price']),
        ]);
    }

    /**
     * Portaal-account aanmaken of het wachtwoord resetten (briefing §10).
     * Zonder SMTP tonen we het wachtwoord eenmalig om door te geven.
     */
    public function storePortalAccount(Customer $customer): RedirectResponse
    {
        if (blank($customer->email)) {
            return back()->with('error', 'Vul eerst een e-mailadres in bij deze klant.');
        }

        $password = Str::password(14);
        $portalUser = $customer->portalUser;

        if ($portalUser !== null) {
            $portalUser->update(['password' => $password]);
            AuditLog::record($portalUser, 'portaal_wachtwoord_reset', [], ['klant' => $customer->name]);

            return back()->with('success', "Nieuw portaalwachtwoord voor {$customer->name}: {$password} - geef dit eenmalig door, het wordt niet nog eens getoond.");
        }

        if (User::where('email', $customer->email)->exists()) {
            return back()->with('error', 'Er bestaat al een account met dit e-mailadres.');
        }

        $portalUser = User::create([
            'name' => $customer->name,
            'email' => $customer->email,
            'role' => UserRole::Klant,
            'customer_id' => $customer->id,
            'password' => $password,
        ]);

        AuditLog::record($portalUser, 'portaal_account_aangemaakt', [], ['klant' => $customer->name]);
        $customer->recordEvent(TimelineEventType::Intern, 'Klantportaal-account aangemaakt', $customer->email);

        return back()->with('success', 'Portaal-account aangemaakt. Inloggen op '.config('app.url')." met {$customer->email} en wachtwoord: {$password} - geef dit eenmalig door.");
    }

    public function storeNote(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $customer->recordEvent(TimelineEventType::Notitie, 'Notitie', $validated['body']);

        return back()->with('success', 'Notitie toegevoegd.');
    }
}
