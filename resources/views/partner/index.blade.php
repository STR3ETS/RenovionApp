@php
    use App\Support\CertificateCatalogue;
    $eur = fn (float $v) => 'EUR '.number_format($v, 2, ',', '.');
@endphp

<x-layouts.portal title="Overzicht" portal="Partnerportaal" home="partner.index">
    <h1 class="text-2xl font-bold text-gray-900">{{ $partner->name }}</h1>
    <p class="mt-1 mb-6 text-sm text-gray-500">
        Hier staan de prijsaanvragen die wij bij u uitzetten, uw opdrachten en uw dossier.
    </p>

    {{-- Het dossier bovenaan als het niet rond is: zolang dat zo is, krijgt u geen werk --}}
    @unless ($dossier['complete'])
        <section class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <h2 class="flex items-center gap-2 font-bold text-gray-900">
                <x-icon name="exclamation-triangle" class="h-5 w-5 text-amber-600" />
                Uw dossier is nog niet compleet
            </h2>
            <p class="mt-1 mb-4 text-sm text-gray-600">
                {{ count($dossier['open']) }} {{ count($dossier['open']) === 1 ? 'punt' : 'punten' }} te gaan.
                Zolang er iets ontbreekt kunnen wij u geen opdracht geven.
            </p>

            <div class="mb-4 space-y-1.5">
                @foreach ($dossier['open'] as $item)
                    <div class="flex items-center gap-2 text-sm">
                        <x-icon name="x-mark" class="h-4 w-4 shrink-0 text-red-500" />
                        <span class="text-gray-700">{{ $item['label'] }}</span>
                        <span class="ml-auto text-xs text-gray-400">{{ CertificateCatalogue::stateLabel($item['state']) }}</span>
                    </div>
                @endforeach
            </div>

            <form method="POST" action="{{ route('partner.document', $partner) }}" enctype="multipart/form-data"
                  class="space-y-3 rounded-xl bg-white p-4">
                @csrf
                <p class="text-sm font-semibold text-gray-900">Stuk aanleveren</p>

                <div class="grid gap-2 sm:grid-cols-2">
                    <select name="type" required
                            class="w-full rounded-xl border-gray-300 py-2 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <optgroup label="Documenten">
                            @foreach ($documentTypes as $type => $label)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="Overeenkomsten, ondertekenen">
                            @foreach ($agreementTypes as $type => $label)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </optgroup>
                    </select>
                    <input type="date" name="valid_until" title="Geldig tot"
                           class="w-full rounded-xl border-gray-300 py-2 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <label class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700">
                        <x-icon name="paper-clip" class="h-4 w-4" /> Bestand kiezen
                        <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" hidden>
                    </label>
                    <button type="submit" class="rounded-xl bg-brand-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-600">
                        Aanleveren
                    </button>
                </div>
            </form>
        </section>
    @else
        <div class="mb-6 flex items-center gap-3 rounded-2xl border border-green-200 bg-green-50 p-4">
            <x-icon name="check" class="h-5 w-5 shrink-0 text-green-600" />
            <p class="text-sm text-gray-700"><b>Uw dossier is compleet.</b> U kunt werk van ons krijgen.</p>
        </div>
    @endunless

    {{-- Prijsaanvragen --}}
    <section class="mb-6">
        <h2 class="mb-3 font-bold text-gray-900">Prijsaanvragen</h2>

        @if ($invitations->isEmpty())
            <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-6 text-center">
                <p class="text-sm text-gray-500">Op dit moment staat er geen aanvraag voor u open.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($invitations as $rfq)
                    @php($own = $rfq->bids->first())
                    <div class="rounded-2xl border border-gray-200 bg-white p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-bold text-gray-900">{{ $rfq->title }}</p>
                                <p class="text-sm text-gray-500">
                                    {{ $rfq->number }}, {{ $rfq->project->city ?: $rfq->project->name }}
                                    @if ($rfq->starts_on)
                                        , gewenste start {{ $rfq->starts_on->translatedFormat('j F') }}
                                    @endif
                                </p>
                            </div>
                            @if ($rfq->deadline)
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold {{ $rfq->deadline->isPast() ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $rfq->deadline->isPast() ? 'Deadline verstreken' : 'Uiterlijk '.$rfq->deadline->translatedFormat('j M') }}
                                </span>
                            @endif
                        </div>

                        @if ($rfq->scope)
                            <p class="mt-3 text-sm whitespace-pre-line text-gray-700">{{ $rfq->scope }}</p>
                        @endif

                        @if ($own)
                            <div class="mt-3 rounded-xl bg-green-50 p-3 text-sm text-gray-700">
                                <b>Uw bieding staat vast:</b> {{ $eur((float) $own->price) }}
                                @if ($own->can_start_on), start {{ $own->can_start_on->translatedFormat('j F') }} @endif.
                                U kunt hem hieronder nog aanpassen zolang wij niet hebben besloten.
                            </div>
                        @endif

                        <form method="POST" action="{{ route('partner.bid', $rfq) }}" class="mt-3 space-y-2 border-t border-gray-200 pt-3">
                            @csrf
                            <div class="grid gap-2 sm:grid-cols-4">
                                <input type="number" step="0.01" min="0" name="price" required
                                       value="{{ $own?->price }}" placeholder="Uw prijs"
                                       class="w-full rounded-xl border-gray-300 py-2 text-sm focus:border-brand-500 focus:ring-brand-500">
                                <input type="date" name="can_start_on" value="{{ $own?->can_start_on?->toDateString() }}" title="Kan starten op"
                                       class="w-full rounded-xl border-gray-300 py-2 text-sm focus:border-brand-500 focus:ring-brand-500">
                                <input type="number" min="1" name="days" value="{{ $own?->days }}" placeholder="Dagen"
                                       class="w-full rounded-xl border-gray-300 py-2 text-sm focus:border-brand-500 focus:ring-brand-500">
                                <input type="number" min="0" max="100" name="completeness" value="{{ $own?->completeness ?? 100 }}"
                                       title="Hoeveel procent van het gevraagde heeft u geprijsd"
                                       class="w-full rounded-xl border-gray-300 py-2 text-sm focus:border-brand-500 focus:ring-brand-500">
                            </div>

                            <input type="text" name="conditions" value="{{ $own?->conditions }}"
                                   placeholder="Voorwaarden, bijvoorbeeld: exclusief steiger en afvoer"
                                   class="w-full rounded-xl border-gray-300 py-2 text-sm focus:border-brand-500 focus:ring-brand-500">

                            <button type="submit" class="rounded-xl bg-brand-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-600">
                                {{ $own ? 'Bieding bijwerken' : 'Bieding insturen' }}
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Gegunde opdrachten --}}
    @if ($awarded->isNotEmpty())
        <section class="mb-6">
            <h2 class="mb-3 font-bold text-gray-900">Uw opdrachten</h2>
            <div class="space-y-2">
                @foreach ($awarded as $rfq)
                    @php($bid = $rfq->winningBid())
                    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-gray-200 bg-white p-4">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-gray-900">{{ $rfq->title }}</p>
                            <p class="truncate text-sm text-gray-500">
                                {{ $rfq->order_number }}, gegund {{ $rfq->awarded_on?->translatedFormat('j F Y') }}
                                @if ($rfq->starts_on), start {{ $rfq->starts_on->translatedFormat('j F') }} @endif
                            </p>
                        </div>
                        <p class="shrink-0 font-bold text-gray-900">{{ $eur((float) ($bid?->price ?? 0)) }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Werkbonnen, als er een inlog aan een medewerker hangt --}}
    @if ($workOrders->isNotEmpty())
        <section>
            <h2 class="mb-3 font-bold text-gray-900">Werkbonnen</h2>
            <div class="space-y-2">
                @foreach ($workOrders as $order)
                    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-gray-200 bg-white p-4">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-gray-900">{{ $order->title }}</p>
                            <p class="truncate text-sm text-gray-500">
                                {{ $order->number }}, {{ $order->date->translatedFormat('j M Y') }}
                            </p>
                        </div>
                        <p class="shrink-0 text-sm text-gray-600">
                            {{ rtrim(rtrim(number_format((float) $order->hours, 2, ',', '.'), '0'), ',') }} uur
                        </p>
                        <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-bold text-gray-600">
                            {{ $order->status->label() }}
                        </span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.portal>
