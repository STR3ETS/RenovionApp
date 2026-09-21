<x-layouts.app title="Klanten">

    <x-page-header title="Klanten" subtitle="{{ $customers->total() }} klanten">
        <form method="GET" action="{{ route('customers.index') }}">
            <input type="search" name="q" value="{{ $search }}" placeholder="Zoek op naam, plaats of e-mail…"
                   class="w-64 rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
        </form>
    </x-page-header>

    <div class="space-y-2">
        @forelse ($customers as $customer)
            <a href="{{ route('customers.show', $customer) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 transition hover:border-brand-400">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-navy-100 text-sm font-bold text-navy-700">
                    {{ str($customer->name)->replace('Familie ', '')->substr(0, 1)->upper() }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-navy-900">{{ $customer->name }}</span>
                    <span class="block text-xs text-gray-500">{{ $customer->city ?? '—' }} · {{ $customer->phone ?? 'geen telefoon' }}</span>
                </span>
                <span class="text-xs text-gray-400">{{ $customer->leads_count }} {{ $customer->leads_count === 1 ? 'aanvraag' : 'aanvragen' }} · {{ $customer->projects_count }} {{ $customer->projects_count === 1 ? 'project' : 'projecten' }}</span>
            </a>
        @empty
            <x-empty-state title="Geen klanten gevonden" />
        @endforelse
    </div>

    <div class="mt-4">
        {{ $customers->links() }}
    </div>

</x-layouts.app>
