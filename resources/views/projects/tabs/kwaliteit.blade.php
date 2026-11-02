@php
    use App\Support\QualityTemplates;
    $open = $snags->filter->isOpen();
@endphp

<div class="grid gap-4 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        {{-- De opleverketen --}}
        <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-bold text-white">Oplever<span class="text-brand-500">keten</span></h2>
                <a href="{{ route('quality.show', $project) }}"
                   class="rounded-full border border-surface-600 px-3.5 py-2 text-xs font-semibold text-slate-300 transition hover:text-white">
                    Kwaliteit openen
                </a>
            </div>

            @php($stap = $review?->step ?? 0)
            <div class="space-y-1.5">
                @foreach (QualityTemplates::FLOW as $index => [$label, $voorwaarde])
                    <div class="flex items-start gap-3 rounded-xl p-2.5 {{ $index === $stap ? 'bg-brand-500/5' : '' }}">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold {{ $index < $stap ? 'bg-green-500/20 text-green-300' : ($index === $stap ? 'bg-brand-500 text-white' : 'bg-surface-700 text-slate-500') }}">
                            @if ($index < $stap)
                                <x-icon name="check" class="h-3 w-3" />
                            @else
                                {{ $index + 1 }}
                            @endif
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold {{ $index <= $stap ? 'text-white' : 'text-slate-400' }}">{{ $label }}</span>
                            <span class="block text-xs text-slate-500">{{ $voorwaarde }}</span>
                        </span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Restpunten --}}
        <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <h2 class="mb-1 text-sm font-bold text-white">Rest<span class="text-brand-500">punten</span></h2>
            <p class="mb-4 text-xs text-slate-500">{{ $open->count() }} open van {{ $snags->count() }}.</p>

            @if ($snags->isEmpty())
                <p class="text-sm text-slate-500">Nog geen restpunten vastgelegd.</p>
            @else
                <div class="space-y-2">
                    @foreach ($snags as $snag)
                        <div class="rounded-xl border bg-surface-850 p-3 {{ $snag->isOverdue() ? 'border-red-500/30' : 'border-surface-700' }}">
                            <div class="flex flex-wrap items-center gap-2">
                                <b class="min-w-0 truncate text-sm font-semibold text-white">{{ $snag->title }}</b>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $snag->badgeClasses() }}">{{ $snag->statusLabel() }}</span>
                                @if ($snag->source === 'klant')
                                    <span class="rounded-full bg-klant-500/15 px-2 py-0.5 text-[10px] font-bold text-klant-300">Van de klant</span>
                                @endif
                            </div>
                            <p class="mt-0.5 text-xs text-slate-400">
                                {{ $snag->location ?: 'geen locatie' }}, {{ $snag->ownerName() }}
                                @if ($snag->due_on), voor {{ $snag->due_on->translatedFormat('j M') }} @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    <div class="space-y-4">
        {{-- Controlepunten --}}
        @if ($review)
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-1 text-sm font-bold text-white">Controle<span class="text-brand-500">punten</span></h2>
                <p class="mb-3 text-xs text-slate-500">
                    {{ $review->applicableChecks()->filter->isChecked()->count() }} van {{ $review->applicableChecks()->count() }} afgevinkt.
                    @if ($review->wkb)
                        Dit project valt onder de Wkb.
                    @endif
                </p>

                <div class="space-y-1.5">
                    @foreach ($review->applicableChecks() as $check)
                        <div class="flex items-start gap-2 text-sm">
                            <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full {{ $check->isChecked() ? 'bg-green-500/20 text-green-300' : 'bg-surface-700 text-slate-600' }}">
                                <x-icon name="check" class="h-2.5 w-2.5" />
                            </span>
                            <span class="min-w-0 {{ $check->isChecked() ? 'text-slate-300' : 'text-slate-400' }}">{{ $check->title }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Servicemeldingen na oplevering --}}
        @if ($serviceTickets->isNotEmpty())
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-3 text-sm font-bold text-white">Service<span class="text-brand-500">meldingen</span></h2>
                <div class="space-y-2">
                    @foreach ($serviceTickets as $ticket)
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="min-w-0 flex-1 truncate text-sm text-slate-200">{{ $ticket->title }}</span>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $ticket->badgeClasses() }}">{{ $ticket->statusLabel() }}</span>
                            </div>
                            <p class="text-xs {{ $ticket->warranty ? 'text-slate-500' : 'text-amber-300' }}">
                                {{ $ticket->number }}, {{ $ticket->warranty ? 'onder garantie' : 'buiten garantie' }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</div>
