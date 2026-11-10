@php
    $afwijking = $order->hoursVariance();
    $afgevinkt = collect($order->checklist ?? [])->where('done', true)->count();
@endphp

<x-layouts.app :title="$order->number">
    <div class="mb-4">
        <a href="{{ route('work-orders.index') }}" class="flex items-center gap-1 text-sm font-semibold text-slate-400 transition hover:text-white">
            <x-icon name="chevron-left" class="h-4 w-4" /> Werkbonnen
        </a>
    </div>

    <x-page-header :title="$order->title" :eyebrow="$order->number" category="project"
                   :subtitle="$order->project->name.', '.($order->user?->name ?? 'niet toegewezen').', '.$order->date->translatedFormat('j F Y')">
        <span class="rounded-full px-3 py-1.5 text-xs font-bold {{ $order->status->badgeClasses() }}">{{ $order->status->label() }}</span>
    </x-page-header>

    @if ($order->rejection_reason)
        <div class="mb-4 flex items-start gap-3 rounded-2xl border border-red-500/25 bg-red-500/10 p-4">
            <x-icon name="exclamation-triangle" class="h-5 w-5 shrink-0 text-red-300" />
            <div>
                <p class="text-sm font-bold text-white">Teruggestuurd naar de uitvoerder</p>
                <p class="mt-0.5 text-sm text-slate-300">{{ $order->rejection_reason }}</p>
            </div>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Uren en materiaal --}}
            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-4 text-sm font-bold text-white">Uren <span class="text-brand-500">en materiaal</span></h2>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div>
                        <p class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">Geboekt</p>
                        <p class="mt-1 text-xl font-bold text-white">{{ rtrim(rtrim(number_format((float) $order->hours, 2, ',', '.'), '0'), ',') }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">Begroot</p>
                        <p class="mt-1 text-xl font-bold text-slate-300">{{ rtrim(rtrim(number_format((float) $order->budget_hours, 2, ',', '.'), '0'), ',') }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">Afwijking</p>
                        <p class="mt-1 text-xl font-bold {{ ($afwijking ?? 0) > 0 ? 'text-red-400' : 'text-green-400' }}">
                            {{ $afwijking === null ? 'n.v.t.' : (($afwijking > 0 ? '+' : '').rtrim(rtrim(number_format($afwijking, 1, ',', '.'), '0'), ',')) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">Loonkosten</p>
                        <p class="mt-1 text-xl font-bold text-white">
                            @if ($order->hourly_rate !== null)
                                EUR {{ number_format($order->laborCost(), 0, ',', '.') }}
                            @else
                                <span class="text-slate-500">nog geen tarief</span>
                            @endif
                        </p>
                    </div>
                </div>

                @if (filled($order->materials))
                    <div class="mt-5 border-t border-surface-700 pt-4">
                        <p class="mb-2 text-[11px] font-bold tracking-widest text-slate-500 uppercase">Gebruikt materiaal</p>
                        <div class="space-y-1.5">
                            @foreach ($order->materials as $post)
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="truncate text-slate-200">{{ $post['description'] }}</span>
                                    <span class="shrink-0 font-semibold text-slate-400">
                                        {{ rtrim(rtrim(number_format($post['quantity'], 2, ',', '.'), '0'), ',') }} {{ $post['unit'] }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($order->notes)
                    <div class="mt-5 border-t border-surface-700 pt-4">
                        <p class="mb-2 text-[11px] font-bold tracking-widest text-slate-500 uppercase">Opmerkingen</p>
                        <p class="text-sm whitespace-pre-line text-slate-300">{{ $order->notes }}</p>
                    </div>
                @endif
            </div>

            {{-- De bevroren checklist --}}
            @if (filled($order->checklist))
                <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    <h2 class="mb-1 text-sm font-bold text-white">Checklist <span class="text-brand-500">op de bon</span></h2>
                    <p class="mb-4 text-xs text-slate-500">
                        {{ $afgevinkt }} van {{ count($order->checklist) }} stappen afgevinkt op het moment van indienen.
                    </p>

                    <div class="space-y-1.5">
                        @foreach ($order->checklist as $stap)
                            <div class="flex items-start gap-2.5">
                                <span class="mt-0.5 flex h-4.5 w-4.5 shrink-0 items-center justify-center rounded-full {{ $stap['done'] ? 'bg-green-500/20 text-green-300' : 'bg-surface-700 text-slate-500' }}">
                                    @if ($stap['done'])
                                        <x-icon name="check" class="h-3 w-3" />
                                    @else
                                        <x-icon name="x-mark" class="h-3 w-3" />
                                    @endif
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm {{ $stap['done'] ? 'text-slate-200' : 'text-slate-500' }}">{{ $stap['title'] }}</span>
                                    @if (filled($stap['note'] ?? null))
                                        <small class="block text-xs text-slate-500">{{ $stap['note'] }}</small>
                                    @endif
                                </span>
                                @if (($stap['photos'] ?? 0) > 0)
                                    <span class="flex shrink-0 items-center gap-1 text-xs text-slate-500">
                                        <x-icon name="camera" class="h-3.5 w-3.5" /> {{ $stap['photos'] }}
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($photos->isNotEmpty())
                <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    <h2 class="mb-4 text-sm font-bold text-white">Foto<span class="text-brand-500">bewijs</span></h2>
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                        @foreach ($photos as $foto)
                            <img src="{{ route('photos.show', $foto) }}" alt="{{ $foto->caption }}"
                                 class="aspect-square w-full rounded-lg object-cover" title="{{ $foto->caption }}">
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            {{-- Goedkeuren of terugsturen --}}
            @if ($canApprove)
                <div class="rounded-2xl border border-amber-500/25 bg-amber-500/10 p-5">
                    <h2 class="mb-1 text-sm font-bold text-white">Ter <span class="text-brand-500">controle</span></h2>
                    <p class="mb-4 text-xs text-slate-300">
                        Goedkeuren boekt de uren als kosten op het project en schuift de voortgang op.
                    </p>

                    <form method="POST" action="{{ route('work-orders.approve', $order) }}">
                        @csrf
                        <button type="submit"
                                class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-brand-500 py-3 text-sm font-bold text-white transition hover:bg-brand-600">
                            <x-icon name="check" class="h-4 w-4" /> Goedkeuren
                        </button>
                    </form>

                    <form method="POST" action="{{ route('work-orders.reject', $order) }}" class="mt-2">
                        @csrf
                        <input type="text" name="reason" required placeholder="Waarom terug naar de uitvoerder?"
                               class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
                        <button type="submit" class="mt-2 w-full rounded-xl border border-surface-600 py-2.5 text-sm font-semibold text-slate-300 transition hover:text-white">
                            Terugsturen
                        </button>
                    </form>
                </div>
            @endif

            @if ($order->approved_at)
                <div class="rounded-2xl border border-green-500/25 bg-green-500/10 p-5">
                    <p class="flex items-center gap-2 text-sm font-bold text-white">
                        <x-icon name="check" class="h-5 w-5 text-green-300" /> Goedgekeurd
                    </p>
                    <p class="mt-1 text-xs text-slate-300">
                        {{ $order->approver?->name }}, {{ $order->approved_at->translatedFormat('j F Y H:i') }}
                    </p>
                </div>
            @endif

            {{-- Handtekening van de klant --}}
            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-3 text-sm font-bold text-white">Afgetekend <span class="text-brand-500">door de klant</span></h2>
                @if ($order->isSigned())
                    <p class="text-sm font-semibold text-white">{{ $order->signed_name }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $order->signed_at->translatedFormat('j F Y H:i') }}</p>
                @else
                    <p class="text-sm text-slate-500">Niet afgetekend. Er was niemand aanwezig, of de uitvoerder heeft het overgeslagen.</p>
                @endif
            </div>

            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-3 text-sm font-bold text-white">Dossier</h2>
                <div class="space-y-2 text-sm">
                    <a href="{{ route('projects.show', $order->project) }}" class="flex items-center gap-2 text-slate-200 transition hover:text-white">
                        <x-icon name="folder" class="h-4 w-4 text-slate-500" /> {{ $order->project->name }}
                    </a>
                    @if ($order->job)
                        <a href="{{ route('jobs.show', $order->schedule_entry_id) }}" class="flex items-center gap-2 text-slate-200 transition hover:text-white">
                            <x-icon name="identification" class="h-4 w-4 text-slate-500" /> De klus op de bouwplaats
                        </a>
                    @endif
                    @if ($order->submitted_at)
                        <p class="text-xs text-slate-500">Ingediend {{ $order->submitted_at->translatedFormat('j M Y H:i') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
