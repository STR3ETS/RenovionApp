<?php

namespace App\Http\Controllers;

use App\Enums\TimelineEventType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->trim()->toString();

        $customers = Customer::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->withCount(['leads', 'projects'])
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'search' => $search,
        ]);
    }

    public function show(Customer $customer): View
    {
        $customer->load([
            'leads' => fn ($query) => $query->latest(),
            'quotes' => fn ($query) => $query->latest(),
            'projects' => fn ($query) => $query->latest(),
            'documents.uploader',
            'timelineEvents.user',
            'portalUser',
        ]);

        return view('customers.show', ['customer' => $customer]);
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

            return back()->with('success', "Nieuw portaalwachtwoord voor {$customer->name}: {$password} — geef dit eenmalig door, het wordt niet nog eens getoond.");
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

        return back()->with('success', 'Portaal-account aangemaakt. Inloggen op '.config('app.url')." met {$customer->email} en wachtwoord: {$password} — geef dit eenmalig door.");
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
