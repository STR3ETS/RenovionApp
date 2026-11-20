@php
    $statuses = [
        'concept' => ['Concept', 'bg-ink/10 text-ink2'],
        'preview' => ['Preview', 'bg-amber-500/15 text-amber-300'],
        'verzonden' => ['Verzonden', 'bg-lead-500/15 text-lead-300'],
        'getekend' => ['Getekend', 'bg-green-500/15 text-green-300'],
    ];
@endphp

<x-layouts.app title="Offertetool">
    <x-page-header title="Offertetool" category="offerte"
                   subtitle="Begroting en offerte in één scherm. De klant opent de offerte met zijn mobiele nummer en een toegangscode, en tekent digitaal.">
        @if ($canCreate)
            <form method="POST" action="{{ route('offer-tool.store') }}" class="flex flex-wrap items-center gap-2">
                @csrf
                <input type="text" name="number" value="{{ old('number', $offers->isEmpty() ? '23102' : '') }}" required maxlength="40"
                       placeholder="Offertenummer" aria-label="Offertenummer"
                       class="w-40 rounded-full border-surface-700 bg-surface-850 py-2.5 text-sm text-ink placeholder:text-faint focus:border-brand-500 focus:ring-brand-500">
                <button type="submit" class="btn pri">
                    <x-icon name="plus" class="h-4 w-4" /> Nieuwe offerte
                </button>
            </form>
        @endif
    </x-page-header>

    @error('number')
        <p class="mb-4 rounded-xl bg-red-500/15 px-4 py-3 text-sm font-medium text-red-300">{{ $message }}</p>
    @enderror

    @if ($offers->isEmpty())
        <x-empty-state icon="document-text" category="offerte" title="Nog geen offerte in de tool"
                       subtitle="Maak hierboven de eerste aan. De tool opent met de standaardbegroting; bestaand werk zet je erin via Importeren in de tool zelf." />
    @else
        <div class="tw overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
            <table class="t">
                <thead>
                    <tr>
                        <th>Offerte</th>
                        <th>Opdrachtgever</th>
                        <th>Status</th>
                        <th>Toegang</th>
                        <th>Laatst opgeslagen</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($offers as $offer)
                        @php
                            [$statusLabel, $statusClass] = $statuses[$offer->status()] ?? ['Alleen begroting', 'bg-ink/10 text-muted'];
                            $open = $offer->openAccessCount();
                            $signature = $offer->signatures->first();
                        @endphp
                        <tr>
                            <td>
                                <b>{{ $offer->number }}</b>
                                <span class="sub">{{ $offer->project() ?: 'Nog niet opgeslagen' }}</span>
                            </td>
                            <td>{{ $offer->client() ?: 'Onbekend' }}</td>
                            <td>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $statusClass }}">{{ $statusLabel }}</span>
                                @if ($offer->version())
                                    <span class="sub">versie {{ $offer->version() }}</span>
                                @endif
                                @if ($signature)
                                    <span class="sub">door {{ $signature->name }}, {{ $signature->signed_on->translatedFormat('j M Y') }}</span>
                                @endif
                            </td>
                            <td>
                                {{ $open }} {{ $open === 1 ? 'persoon' : 'personen' }} met een geldige code
                            </td>
                            <td>
                                @if ($offer->saved_at)
                                    {{ $offer->saved_at->diffForHumans() }}
                                    @if ($offer->savedBy)
                                        <span class="sub">{{ $offer->savedBy->name }}</span>
                                    @endif
                                @else
                                    Nog niet
                                @endif
                            </td>
                            <td class="r whitespace-nowrap">
                                <span x-data="{ copied: false }" class="inline-flex items-center gap-2">
                                    <button type="button"
                                            @click="navigator.clipboard.writeText(@js(route('offer-tool.client', $offer))).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                            class="btn sm">
                                        <span x-show="!copied">Klantlink kopiëren</span>
                                        <span x-show="copied" x-cloak>Gekopieerd</span>
                                    </button>
                                    <a href="{{ route('offer-tool.show', $offer) }}"
                                       class="btn pri sm">
                                        Openen
                                    </a>
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="mt-3 text-xs text-faint">
            Toegangscodes maak je in de tool zelf, met de knop Toegang. De code staat nergens opgeslagen, alleen een controlegetal:
            geef hem dus direct door aan de klant.
        </p>

        @unless ($canCreate)
            <div class="mt-4 flex items-start gap-3 rounded-2xl border border-amber-500/25 bg-amber-500/10 p-4">
                <x-icon name="exclamation-triangle" class="h-5 w-5 shrink-0 text-amber-300" />
                <p class="text-sm text-ink">
                    <b class="text-ink">De tool staat op één offerte.</b>
                    In de broncode van de tool staan nog de voorbeeldgegevens van dit project. Een klant van een ander project
                    zou die kunnen lezen, dus een tweede offerte kan pas als de tool is opgeschoond.
                </p>
            </div>
        @endunless
    @endif
</x-layouts.app>
