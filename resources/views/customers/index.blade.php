<x-layouts.app title="Klanten">

    <x-page-header eyebrow="Relaties" category="klant" title="Klanten" subtitle="{{ \App\Support\Tally::of($customers->total(), 'klant', 'klanten') }}">
        <form method="GET" action="{{ route('customers.index') }}" class="app:w-full">
            <input aria-label="Zoeken" type="search" name="q" value="{{ $search }}" placeholder="Zoek op naam, plaats of e-mail…"
                   class="w-64 app:w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
        </form>
    </x-page-header>

    @nativeapp
        {{-- iOS/Android-app (prototype Renovion OS): één kaart, per klant de kerngegevens --}}
        @if ($customers->isEmpty())
            <x-empty-state title="Geen klanten gevonden" icon="users" category="klant" />
        @else
            <div class="app-card">
                @foreach ($customers as $customer)
                    <a href="{{ route('customers.show', $customer) }}" class="app-rec">
                        <span class="flex items-center gap-2.5">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-klant-500 text-[11px] font-bold text-white">
                                {{ str($customer->name)->replace('Familie ', '')->explode(' ')->take(2)->map(fn ($deel) => str($deel)->substr(0, 1)->upper())->implode('') }}
                            </span>
                            <h4 class="truncate">{{ $customer->name }}</h4>
                        </span>
                        <span class="app-fields">
                            <span>
                                <span class="app-lbl">Plaats</span>
                                <b>{{ $customer->city ?? 'Onbekend' }}</b>
                            </span>
                            <span>
                                <span class="app-lbl">Contact</span>
                                <b>{{ $customer->phone ?? 'Geen telefoon' }}</b>
                                @if ($customer->email)
                                    <small class="truncate">{{ $customer->email }}</small>
                                @endif
                            </span>
                            <span>
                                <span class="app-lbl">Dossiers</span>
                                <b>{{ \App\Support\Tally::of($customer->leads_count, 'aanvraag', 'aanvragen') }}, {{ \App\Support\Tally::of($customer->projects_count, 'project', 'projecten') }}</b>
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    @else
    <div class="space-y-2">
        @forelse ($customers as $customer)
            <a href="{{ route('customers.show', $customer) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 border-l-4 border-l-klant-500 bg-white p-3 transition hover:shadow-sm">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-klant-100 text-sm font-bold text-klant-700">
                    {{ str($customer->name)->replace('Familie ', '')->substr(0, 1)->upper() }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-navy-900">{{ $customer->name }}</span>
                    <span class="block text-xs text-gray-500">{{ $customer->city ?? '-' }}, {{ $customer->phone ?? 'geen telefoon' }}</span>
                </span>
                <span class="text-xs text-gray-500">{{ $customer->leads_count }} {{ $customer->leads_count === 1 ? 'aanvraag' : 'aanvragen' }}, {{ $customer->projects_count }} {{ $customer->projects_count === 1 ? 'project' : 'projecten' }}</span>
            </a>
        @empty
            <x-empty-state title="Geen klanten gevonden" icon="users" category="klant" />
        @endforelse
    </div>
    @endnativeapp

    <div class="mt-4">
        {{ $customers->links() }}
    </div>

</x-layouts.app>
