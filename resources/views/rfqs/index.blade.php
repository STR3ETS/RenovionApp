@php
    use App\Models\Rfq;
    $eur = fn (float $v) => 'EUR '.number_format($v, 0, ',', '.');
@endphp

<x-layouts.app title="Prijsaanvragen">
    <x-page-header title="Prijsaanvragen" eyebrow="Mensen en partners" category="klant"
                   subtitle="Werk uitzetten, biedingen vergelijken tegen de begroting en gunnen.">
        <a href="{{ route('rfqs.create') }}"
           class="flex items-center gap-1.5 rounded-full bg-brand-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
            <x-icon name="plus" class="h-4 w-4" /> Nieuwe aanvraag
        </a>
    </x-page-header>

    <section class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-3">
        @foreach ([
            ['Verplichtingen', $eur($commitment), 'gegund aan partners', 'bg-brand-500', 'text-brand-400'],
            [$savings >= 0 ? 'Onder begroting' : 'Boven begroting', $eur(abs($savings)), 'over alle gunningen', $savings >= 0 ? 'bg-green-500' : 'bg-red-500', $savings >= 0 ? 'text-green-400' : 'text-red-400'],
            ['Uitgezet', (string) ($counts[Rfq::STATUS_SENT] ?? 0), 'wachten op biedingen', 'bg-lead-500', 'text-lead-400'],
        ] as [$label, $value, $sub, $accent, $labelClass])
            <div class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
                <span class="block h-[3px] w-full {{ $accent }}"></span>
                <div class="p-4">
                    <p class="text-[11px] font-bold tracking-widest uppercase {{ $labelClass }}">{{ $label }}</p>
                    <p class="mt-1 text-lg font-bold text-white">{{ $value }}</p>
                    <p class="mt-0.5 text-xs text-slate-400">{{ $sub }}</p>
                </div>
            </div>
        @endforeach
    </section>

    <div class="scrollbar-none mb-4 flex gap-2 overflow-x-auto">
        <a href="{{ route('rfqs.index') }}"
           class="flex shrink-0 items-center gap-1.5 rounded-full border px-3.5 py-2 text-sm font-semibold transition {{ $status === '' ? 'border-brand-500 bg-brand-500/10 text-white' : 'border-surface-700 text-slate-400 hover:text-slate-200' }}">
            Alles <span class="text-xs text-slate-500">{{ $counts->sum() }}</span>
        </a>
        @foreach (Rfq::STATUSES as $key => $label)
            @continue(($counts[$key] ?? 0) === 0 && $status !== $key)
            <a href="{{ route('rfqs.index', ['status' => $key]) }}"
               class="flex shrink-0 items-center gap-1.5 rounded-full border px-3.5 py-2 text-sm font-semibold transition {{ $status === $key ? 'border-brand-500 bg-brand-500/10 text-white' : 'border-surface-700 text-slate-400 hover:text-slate-200' }}">
                {{ $label }} <span class="text-xs text-slate-500">{{ $counts[$key] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    @if ($rfqs->isEmpty())
        <x-empty-state icon="scale" category="klant" title="Nog geen prijsaanvragen"
                       subtitle="Zet een stuk werk uit bij een paar partners en vergelijk de biedingen.">
            <a href="{{ route('rfqs.create') }}" class="inline-flex items-center gap-1.5 rounded-full bg-brand-500 px-4 py-2.5 text-sm font-bold text-white">
                <x-icon name="plus" class="h-4 w-4" /> Nieuwe aanvraag
            </a>
        </x-empty-state>
    @else
        <div class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
            @foreach ($rfqs as $rfq)
                @php($beste = $rfq->bestComparableBid())
                <a href="{{ route('rfqs.show', $rfq) }}"
                   class="flex items-center gap-3 border-b border-surface-700 border-l-4 p-4 transition last:border-b-0 hover:bg-white/5 {{ $rfq->isOverdue() ? 'border-l-amber-400' : 'border-l-transparent' }}">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-klant-500/15 text-klant-400">
                        <x-icon name="scale" class="h-5 w-5" />
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <b class="truncate text-sm font-semibold text-white">{{ $rfq->title }}</b>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $rfq->badgeClasses() }}">{{ $rfq->statusLabel() }}</span>
                            @if ($rfq->isOverdue())
                                <span class="rounded-full bg-amber-500/15 px-2 py-0.5 text-[10px] font-bold text-amber-300">Deadline voorbij</span>
                            @endif
                        </span>
                        <small class="mt-0.5 block truncate text-xs text-slate-400">
                            {{ $rfq->number }}, {{ $rfq->project->name }},
                            {{ $rfq->invited->count() }} {{ $rfq->invited->count() === 1 ? 'partij' : 'partijen' }} uitgenodigd,
                            {{ $rfq->bids->count() }} {{ $rfq->bids->count() === 1 ? 'bieding' : 'biedingen' }}
                        </small>
                    </span>

                    <span class="shrink-0 text-right">
                        <b class="block text-sm font-bold text-white">{{ $eur((float) $rfq->budget) }}</b>
                        @if ($rfq->winner)
                            <small class="block text-xs {{ ($rfq->savings() ?? 0) >= 0 ? 'text-green-400' : 'text-red-400' }}">
                                {{ $rfq->winner->name }}, {{ $eur((float) ($rfq->winningBid()?->price ?? 0)) }}
                            </small>
                        @elseif ($beste)
                            <small class="block text-xs text-slate-500">beste bieding {{ $eur((float) $beste->price) }}</small>
                        @else
                            <small class="block text-xs text-slate-500">begroot</small>
                        @endif
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
