<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\InboundMail;
use App\Services\AttentionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Opdracht inschieten: een zakelijke klant geeft via zijn persoonlijke link
 * een opdracht door, zonder in te loggen. Het klantnummer staat erbij, dus de
 * opdracht komt herkend binnen in de aanvraagmail.
 */
class CustomerOrderController extends Controller
{
    public function create(string $token): View
    {
        return view('customer-orders.create', ['customer' => $this->customer($token), 'token' => $token]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $customer = $this->customer($token);

        $validated = $request->validate([
            'contact' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'work' => ['required', 'string', 'max:5000'],
            'deadline' => ['nullable', 'date', 'after_or_equal:today'],
            // Een veld dat een mens nooit ziet en dus nooit invult.
            'website' => ['nullable', 'prohibited'],
        ], [
            'deadline.after_or_equal' => 'Kies een datum vanaf vandaag.',
        ]);

        InboundMail::create([
            'from_name' => $validated['contact'],
            'from_email' => $customer->email,
            'subject' => str($validated['work'])->squish()->limit(120)->value(),
            'body' => $validated['work'],
            // Via de link weten we zeker wie het is: het bericht is meteen gekoppeld.
            'status' => InboundMail::STATUS_LINKED,
            'source' => InboundMail::SOURCE_PORTAL,
            'customer_id' => $customer->id,
            'received_at' => now(),
            'extracted' => [
                'werk' => str($validated['work'])->squish()->limit(160)->value(),
                'adres' => $validated['address'],
                'deadline' => $validated['deadline'] ?? null,
                'klant_id' => $customer->id,
                'klant_reden' => 'via de persoonlijke opdrachtlink',
                'nieuwe_klant' => false,
                'bron' => 'portaal',
            ],
        ]);

        AttentionService::forgetCount();

        return redirect()->route('customer-orders.create', $token)
            ->with('success', 'Uw opdracht is binnen. We nemen contact met u op over de planning en de prijs.');
    }

    /** Alleen een zakelijke klant heeft een opdrachtlink; een onbekende link bestaat niet. */
    private function customer(string $token): Customer
    {
        $customer = Customer::where('portal_token', $token)->firstOrFail();

        abort_unless($customer->isBusiness(), 404);

        return $customer;
    }
}
