<x-layouts.app title="Prijslijst">

    <div x-data="{ nieuw: {{ $errors->any() ? 'true' : 'false' }}, bewerk: null }">

        <x-page-header eyebrow="Kostendatabase" category="calc" title="Prijslijst" subtitle="{{ $items->count() }} artikelen, Nova gebruikt deze lijst voor calculaties en offertes">
            <form method="GET" action="{{ route('price-items.index') }}">
                @if ($type)<input type="hidden" name="type" value="{{ $type->value }}">@endif
                <input type="search" name="q" value="{{ $q }}" placeholder="Zoek op naam of artikelnummer…"
                       class="w-64 rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
            </form>
            <button @click="nieuw = !nieuw" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-600">+ Artikel</button>
        </x-page-header>

        {{-- Nieuw artikel --}}
        <form x-show="nieuw" x-cloak method="POST" action="{{ route('price-items.store') }}"
              class="mb-4 grid gap-3 rounded-2xl border border-calc-200 bg-calc-50 p-4 sm:grid-cols-2 lg:grid-cols-6">
            @csrf
            <input type="hidden" name="q" value="{{ $q }}">
            @if ($type)<input type="hidden" name="type" value="{{ $type->value }}">@endif
            <input type="text" name="name" required placeholder="Naam van het artikel *" value="{{ old('name') }}"
                   class="rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500 sm:col-span-2">
            <select name="type" class="rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                @foreach (\App\Enums\CalculationLineType::cases() as $lineType)
                    <option value="{{ $lineType->value }}" @selected(old('type') === $lineType->value)>{{ $lineType->label() }}</option>
                @endforeach
            </select>
            <select name="unit" class="rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                @foreach (['m2', 'm1', 'uur', 'stuk', 'post'] as $unit)
                    <option value="{{ $unit }}" @selected(old('unit') === $unit)>{{ $unit }}</option>
                @endforeach
            </select>
            <input type="number" name="unit_price" required min="0" step="0.01" placeholder="Prijs excl. btw *" value="{{ old('unit_price') }}"
                   class="rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
            <button type="submit" class="rounded-xl bg-navy-950 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-900">Toevoegen</button>
            @error('name')<p class="text-xs text-red-600 sm:col-span-2 lg:col-span-6">{{ $message }}</p>@enderror
            @error('unit_price')<p class="text-xs text-red-600 sm:col-span-2 lg:col-span-6">{{ $message }}</p>@enderror
            <p class="text-xs text-gray-500 sm:col-span-2 lg:col-span-6">Het artikelnummer wordt automatisch toegekend (ARB, MAT, MTR, OND of STP plus volgnummer).</p>
        </form>

        {{-- Typefilter --}}
        <div class="mb-4 flex flex-wrap gap-1.5">
            <a href="{{ route('price-items.index', array_filter(['q' => $q])) }}"
               class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $type === null ? 'bg-navy-900 text-white' : 'border border-gray-300 bg-white text-gray-600' }}">Alle</a>
            @foreach (\App\Enums\CalculationLineType::cases() as $lineType)
                <a href="{{ route('price-items.index', array_filter(['type' => $lineType->value, 'q' => $q])) }}"
                   class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $type === $lineType ? 'bg-navy-900 text-white' : 'border border-gray-300 bg-white text-gray-600' }}">{{ $lineType->label() }}</a>
            @endforeach
        </div>

        {{-- Artikelen --}}
        <div class="space-y-2">
            @forelse ($items as $item)
                <div class="rounded-xl border border-gray-200 border-l-4 border-l-calc-500 bg-white {{ $item->active ? '' : 'opacity-60' }}">
                    <div x-show="bewerk !== {{ $item->id }}" class="flex flex-wrap items-center gap-3 p-3">
                        <span class="w-20 shrink-0 rounded-lg bg-calc-100 py-1 text-center font-mono text-xs font-bold text-calc-700">{{ $item->code }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-navy-900">{{ $item->name }}</span>
                            <span class="block text-xs text-gray-500">
                                {{ $item->source }} ({{ $item->edition }}){{ (float) $item->surcharge_pct > 0 ? ', opslag '.rtrim(rtrim(number_format((float) $item->surcharge_pct, 2, ',', '.'), '0'), ',').'%' : '' }}
                            </span>
                        </span>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $item->type->badgeClasses() }}">{{ $item->type->label() }}</span>
                        <span class="w-24 text-right text-sm font-bold whitespace-nowrap text-navy-900">€ {{ number_format($item->effectivePrice(), 2, ',', '.') }} <span class="text-xs font-medium text-gray-400">/ {{ $item->unit }}</span></span>
                        <button type="button" @click="bewerk = {{ $item->id }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50">Bewerken</button>
                        <form method="POST" action="{{ route('price-items.update', $item) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="toggle" value="1">
                            <input type="hidden" name="q" value="{{ $q }}">
                            @if ($type)<input type="hidden" name="type" value="{{ $type->value }}">@endif
                            <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $item->active ? 'bg-project-100 text-project-700 hover:bg-project-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}"
                                    title="{{ $item->active ? 'Actief: klik om uit te zetten' : 'Uit: klik om weer te activeren' }}">
                                {{ $item->active ? 'Actief' : 'Uit' }}
                            </button>
                        </form>
                    </div>

                    {{-- Inline bewerken --}}
                    <form x-show="bewerk === {{ $item->id }}" x-cloak method="POST" action="{{ route('price-items.update', $item) }}"
                          class="grid gap-3 p-3 sm:grid-cols-2 lg:grid-cols-6">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="q" value="{{ $q }}">
                        <input type="text" name="name" required value="{{ $item->name }}"
                               class="rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500 sm:col-span-2">
                        <select name="type" class="rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                            @foreach (\App\Enums\CalculationLineType::cases() as $lineType)
                                <option value="{{ $lineType->value }}" @selected($item->type === $lineType)>{{ $lineType->label() }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="unit" required value="{{ $item->unit }}" maxlength="10"
                               class="rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <div class="flex items-center gap-2">
                            <input type="number" name="unit_price" required min="0" step="0.01" value="{{ (float) $item->unit_price }}"
                                   class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                            <input type="number" name="surcharge_pct" min="0" max="100" step="0.1" value="{{ (float) $item->surcharge_pct }}" title="Opslag %"
                                   class="w-20 rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="flex-1 rounded-xl bg-navy-950 py-2 text-sm font-semibold text-white transition hover:bg-navy-900">Opslaan</button>
                            <button type="button" @click="bewerk = null" class="rounded-xl border border-gray-300 px-3 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Annuleren</button>
                        </div>
                    </form>
                </div>
            @empty
                <x-empty-state title="Geen artikelen gevonden" subtitle="Pas de zoekterm of het typefilter aan, of voeg een artikel toe." icon="calculator" category="calc" />
            @endforelse
        </div>
    </div>

</x-layouts.app>
