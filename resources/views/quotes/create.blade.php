<x-layouts.app title="Nieuwe offerte">

    <x-page-header title="Nieuwe offerte" :subtitle="$lead ? 'Voor aanvraag van '.$lead->customer->name : null" />

    <form method="POST" action="{{ route('quotes.store') }}" class="max-w-4xl space-y-5">
        @csrf
        @if ($lead)
            <input type="hidden" name="lead_id" value="{{ $lead->id }}">
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-5">
            <div class="grid gap-4 sm:grid-cols-2">
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
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5">
            <h2 class="mb-4 text-sm font-bold text-navy-900">Werkzaamheden</h2>
            @include('quotes.partials.line-editor', [
                'initialLines' => old('lines', [
                    ['description' => $lead?->service ?? '', 'quantity' => 1, 'unit' => 'm²', 'unit_price' => '', 'vat_rate' => 21],
                ]),
            ])
            @error('lines')
                <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5">
            <x-field label="Opmerkingen (intern)" name="notes">
                <textarea name="notes" id="notes" rows="2" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('notes') }}</textarea>
            </x-field>
        </section>

        <div class="flex gap-2">
            <button type="submit" class="rounded-xl bg-brand-600 px-6 py-3 text-sm font-bold text-white transition hover:bg-brand-500">Offerte opslaan als concept</button>
            <a href="{{ route('quotes.index') }}" class="rounded-xl border border-gray-300 bg-white px-6 py-3 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Annuleren</a>
        </div>
    </form>

</x-layouts.app>
