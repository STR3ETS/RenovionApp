<?php

namespace App\Http\Controllers;

use App\Enums\TimelineEventType;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);

        return view('customers.show', ['customer' => $customer]);
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
