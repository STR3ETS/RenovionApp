<x-layouts.app title="Nieuwe offerte">

    <x-page-header title="Nieuwe offerte" :subtitle="$lead ? 'Voor aanvraag van '.$lead->customer->name : 'Je start zo meteen direct in de offerte-builder'" />

    <form method="POST" action="{{ route('quotes.store') }}" class="max-w-xl space-y-5">
        @csrf
        @if ($lead)
            <input type="hidden" name="lead_id" value="{{ $lead->id }}">
        @endif

        <section class="space-y-4 rounded-2xl border border-gray-200 bg-white p-5">
            <x-field label="Klant *" name="customer_id">
                <select name="customer_id" id="customer_id" required class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">— Kies klant —</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(old('customer_id', $lead?->customer_id) == $customer->id)>{{ $customer->name }} ({{ $customer->city ?? '—' }})</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Geldig tot" name="valid_until">
                <input type="date" name="valid_until" id="valid_until" value="{{ old('valid_until', now()->addDays(30)->toDateString()) }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
            </x-field>
            <x-field label="Opmerkingen (intern)" name="notes">
                <textarea name="notes" id="notes" rows="2" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('notes') }}</textarea>
            </x-field>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
            <p class="font-semibold text-navy-900">Zo werkt het</p>
            <p class="mt-1">De offerte opent direct in de builder: daar bouw je het document uit blokken, met templates en Nova. De posten en bedragen komen uit de calculatie, of voeg je daarna toe via "Posten bewerken".</p>
        </section>

        <div class="flex gap-2">
            <button type="submit" class="rounded-xl bg-brand-500 px-6 py-3 text-sm font-bold text-white transition hover:bg-brand-600">Start de offerte-builder</button>
            <a href="{{ route('quotes.index') }}" class="rounded-xl border border-gray-300 bg-white px-6 py-3 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Annuleren</a>
        </div>
    </form>

</x-layouts.app>
