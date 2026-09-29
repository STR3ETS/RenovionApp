{{-- Het opleverrapport (briefing §13), gerenderd uit de bevroren snapshot. --}}
@php $s = $report->snapshot; @endphp

<div class="space-y-8">
    {{-- Kop --}}
    <header class="border-b border-gray-100 pb-5">
        <p class="text-xs font-bold tracking-wide text-brand-500 uppercase">Opleverrapport</p>
        <h1 class="mt-1 text-2xl font-bold text-navy-950">{{ $s['project']['name'] }}</h1>
        <p class="mt-1 text-sm text-gray-500">
            {{ $s['project']['customer'] }}{{ filled($s['project']['address']) ? ' · '.$s['project']['address'] : '' }}
            @if ($s['project']['quote_number']) · offerte {{ $s['project']['quote_number'] }} @endif
        </p>
        <p class="mt-1 text-xs text-gray-400">Opgesteld op {{ $report->generated_at->translatedFormat('j F Y') }}{{ $report->generator ? ' door '.$report->generator->name : '' }}</p>
    </header>

    {{-- Planning --}}
    <section>
        <h2 class="mb-3 text-lg font-bold text-navy-950">Planning</h2>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl bg-gray-50 p-3"><p class="text-xs text-gray-500">Gestart</p><p class="text-sm font-bold text-navy-900">{{ $s['project']['start_date'] ? \Illuminate\Support\Carbon::parse($s['project']['start_date'])->translatedFormat('j M Y') : '—' }}</p></div>
            <div class="rounded-xl bg-gray-50 p-3"><p class="text-xs text-gray-500">Verwacht</p><p class="text-sm font-bold text-navy-900">{{ $s['project']['end_date_expected'] ? \Illuminate\Support\Carbon::parse($s['project']['end_date_expected'])->translatedFormat('j M Y') : '—' }}</p></div>
            <div class="rounded-xl bg-gray-50 p-3"><p class="text-xs text-gray-500">Opgeleverd</p><p class="text-sm font-bold text-navy-900">{{ \Illuminate\Support\Carbon::parse($s['project']['end_date_actual'])->translatedFormat('j M Y') }}</p></div>
            <div class="rounded-xl bg-gray-50 p-3">
                <p class="text-xs text-gray-500">Afwijking</p>
                @if (($s['project']['deviation_days'] ?? null) === null)
                    <p class="text-sm font-bold text-gray-500">—</p>
                @elseif ($s['project']['deviation_days'] > 0)
                    <p class="text-sm font-bold text-amber-600">+{{ $s['project']['deviation_days'] }} dagen</p>
                @else
                    <p class="text-sm font-bold text-green-600">Op schema</p>
                @endif
            </div>
        </div>
    </section>

    {{-- Scope --}}
    @if (filled($s['project']['scope']))
        <section>
            <h2 class="mb-2 text-lg font-bold text-navy-950">Uitgevoerde scope</h2>
            <p class="text-sm whitespace-pre-line text-gray-600">{{ $s['project']['scope'] }}</p>
        </section>
    @endif

    {{-- Fasen --}}
    <section>
        <h2 class="mb-3 text-lg font-bold text-navy-950">Fasen</h2>
        <div class="overflow-hidden rounded-xl border border-gray-200">
            <table class="w-full text-sm">
                <tbody>
                    @foreach ($s['phases'] as $fase)
                        <tr class="border-b border-gray-100 last:border-0">
                            <td class="px-4 py-2.5 font-medium text-navy-900">{{ $fase['name'] }}</td>
                            <td class="px-4 py-2.5 text-xs text-gray-500">
                                @if ($fase['approved_by']) vrijgegeven door {{ $fase['approved_by'] }} @endif
                                @if ($fase['client_approved_at']) · klant akkoord {{ \Illuminate\Support\Carbon::parse($fase['client_approved_at'])->translatedFormat('j M') }} @endif
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $fase['done'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $fase['status'] }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- Uitgevoerd werk met bewijs --}}
    @if (count($s['work']))
        <section>
            <h2 class="mb-3 text-lg font-bold text-navy-950">Uitgevoerd werk en bewijs</h2>
            <div class="space-y-4">
                @foreach ($s['work'] as $pakket)
                    <div class="rounded-xl border border-gray-200 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-sm font-bold text-navy-900">{{ $pakket['name'] }}</p>
                                <p class="text-xs text-gray-400">{{ $pakket['phase'] }}{{ $pakket['responsible'] ? ' · '.$pakket['responsible'] : '' }}</p>
                            </div>
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $pakket['done'] ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">{{ $pakket['status'] }}</span>
                        </div>
                        @if (count($pakket['items']))
                            <ul class="mt-3 space-y-1">
                                @foreach ($pakket['items'] as $item)
                                    <li class="flex items-center gap-1.5 text-sm text-gray-600">
                                        <x-icon name="check" class="h-3.5 w-3.5 shrink-0 text-green-600" />
                                        {{ $item['label'] }}
                                        <span class="text-xs text-gray-400">{{ collect([$item['done_by'], $item['done_at'] ? \Illuminate\Support\Carbon::parse($item['done_at'])->translatedFormat('j M') : null])->filter()->implode(' · ') }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @php $pakketFotos = collect($pakket['photo_ids'])->map(fn ($id) => $photos->get($id))->filter(); @endphp
                        @if ($pakketFotos->isNotEmpty())
                            <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-6">
                                @foreach ($pakketFotos as $foto)
                                    <img src="{{ route('photos.show', $foto) }}" alt="{{ $foto->caption ?? 'Bewijsfoto' }}" loading="lazy" class="h-20 w-full rounded-lg object-cover">
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Algemene foto's --}}
    @php $algemeneFotos = collect($s['general_photo_ids'] ?? [])->map(fn ($id) => $photos->get($id))->filter(); @endphp
    @if ($algemeneFotos->isNotEmpty())
        <section>
            <h2 class="mb-3 text-lg font-bold text-navy-950">Foto's van het resultaat</h2>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach ($algemeneFotos as $foto)
                    <img src="{{ route('photos.show', $foto) }}" alt="{{ $foto->caption ?? 'Projectfoto' }}" loading="lazy" class="h-28 w-full rounded-lg object-cover">
                @endforeach
            </div>
        </section>
    @endif

    {{-- Wijzigingen --}}
    @if (count($s['changes']))
        <section>
            <h2 class="mb-3 text-lg font-bold text-navy-950">Meerwerk en goedgekeurde wijzigingen</h2>
            <ul class="space-y-1.5">
                @foreach ($s['changes'] as $wijziging)
                    <li class="flex items-baseline gap-2 text-sm text-gray-600">
                        <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-bold text-gray-500">{{ $wijziging['version'] }}</span>
                        {{ $wijziging['note'] }}
                        <span class="text-xs text-gray-400">{{ \Illuminate\Support\Carbon::parse($wijziging['date'])->translatedFormat('j M Y') }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Restpunten --}}
    <section>
        <h2 class="mb-3 text-lg font-bold text-navy-950">Restpunten</h2>
        @if (count($s['leftovers']) === 0)
            <p class="flex items-center gap-1.5 text-sm font-medium text-green-700"><x-icon name="check" class="h-4 w-4" /> Geen restpunten — alles is afgerond.</p>
        @else
            <div class="overflow-hidden rounded-xl border border-gray-200">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left text-xs font-bold text-gray-500 uppercase">
                            <th class="px-4 py-2">Punt</th>
                            <th class="px-4 py-2">Eigenaar</th>
                            <th class="px-4 py-2">Deadline</th>
                            <th class="px-4 py-2 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($s['leftovers'] as $punt)
                            <tr class="border-t border-gray-100">
                                <td class="px-4 py-2.5 font-medium text-navy-900">{{ $punt['title'] }} <span class="text-xs text-gray-400">({{ $punt['type'] }})</span></td>
                                <td class="px-4 py-2.5 text-gray-600">{{ $punt['owner'] ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-gray-600">{{ $punt['deadline'] ? \Illuminate\Support\Carbon::parse($punt['deadline'])->translatedFormat('j M Y') : '—' }}</td>
                                <td class="px-4 py-2.5 text-right"><span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">{{ $punt['status'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Garantie --}}
    <section>
        <h2 class="mb-2 text-lg font-bold text-navy-950">Garantie en nazorg</h2>
        <p class="text-sm whitespace-pre-line text-gray-600">{{ $s['warranty'] }}</p>
    </section>

    {{-- Handtekeningen --}}
    <section class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-bold tracking-wide text-gray-400 uppercase">Namens Renovion</p>
            @if ($report->company_signed_at)
                <p class="mt-2 text-sm font-bold text-navy-900">{{ $report->companySigner?->name ?? 'Renovion' }}</p>
                <p class="text-xs text-gray-400">{{ $report->company_signed_at->translatedFormat('j F Y H:i') }}</p>
                <p class="mt-1 flex items-center gap-1 text-xs font-semibold text-green-700"><x-icon name="check" class="h-3.5 w-3.5" /> Digitaal ondertekend</p>
            @else
                <p class="mt-2 text-sm text-gray-400">Nog niet ondertekend</p>
            @endif
        </div>
        <div class="rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-bold tracking-wide text-gray-400 uppercase">Namens de opdrachtgever</p>
            @if ($report->client_signed_at)
                <p class="mt-2 text-sm font-bold text-navy-900">{{ $report->client_signed_name }}</p>
                <p class="text-xs text-gray-400">{{ $report->client_signed_at->translatedFormat('j F Y H:i') }} · IP {{ $report->client_signed_ip }}</p>
                <p class="mt-1 flex items-center gap-1 text-xs font-semibold text-green-700"><x-icon name="check" class="h-3.5 w-3.5" /> Digitaal ondertekend</p>
            @else
                <p class="mt-2 text-sm text-gray-400">Nog niet ondertekend</p>
            @endif
        </div>
    </section>
</div>
