<x-layouts.app :title="'Offerte '.$quote->number">

    <x-page-header :title="'Bewerken: '.$quote->number" :subtitle="$quote->customer->name" />

    <form method="POST" action="{{ route('quotes.update', $quote) }}" class="max-w-4xl space-y-5">
        @csrf
        @method('PATCH')

        <section class="rounded-2xl border border-gray-200 bg-white p-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Geldig tot" name="valid_until">
                    <input type="date" name="valid_until" id="valid_until" value="{{ old('valid_until', $quote->valid_until?->toDateString()) }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5">
            <h2 class="mb-4 text-sm font-bold text-navy-900">Werkzaamheden</h2>
            @include('quotes.partials.line-editor', [
                'initialLines' => old('lines', $quote->lines->map(fn ($line) => [
                    'description' => $line->description,
                    'quantity' => (float) $line->quantity,
                    'unit' => $line->unit,
                    'unit_price' => (float) $line->unit_price,
                    'vat_rate' => (float) $line->vat_rate,
                ])->all()),
            ])
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5">
            <x-field label="Opmerkingen (intern)" name="notes">
                <textarea name="notes" id="notes" rows="2" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('notes', $quote->notes) }}</textarea>
            </x-field>
        </section>

        <div class="flex gap-2">
            <button type="submit" class="rounded-xl bg-brand-600 px-6 py-3 text-sm font-bold text-white transition hover:bg-brand-500">Opslaan</button>
            <a href="{{ route('quotes.show', $quote) }}" class="rounded-xl border border-gray-300 bg-white px-6 py-3 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Annuleren</a>
        </div>
    </form>

</x-layouts.app>
