@php
    use App\Support\Academy;
@endphp

<x-layouts.app title="Academy">
    <x-page-header title="Academy" eyebrow="Bedrijf" category="calc"
                   subtitle="Rondleidingen per onderwerp, een leerpad voor jouw rol, en de afspraken van het bedrijf.">
        <span class="rounded-full bg-calc-500/15 px-3 py-1.5 text-xs font-bold text-calc-300">
            {{ $done }} van {{ $total }} afgerond
        </span>
    </x-page-header>

    {{-- Jouw leerpad eerst --}}
    @php($own = $paths[$ownPath] ?? null)
    @if ($own)
        @php($p = $pathProgress[$ownPath])
        <div class="mb-5 rounded-2xl border border-brand-500/25 bg-brand-500/5 p-5">
            <div class="flex flex-wrap items-center gap-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ Academy::chipClasses($own['colour']) }}">
                    <x-icon :name="$own['icon']" class="h-6 w-6" />
                </span>

                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-bold tracking-widest text-brand-400 uppercase">Jouw leerpad</p>
                    <h2 class="text-base font-bold text-white">{{ $own['title'] }}</h2>
                    <p class="text-xs text-slate-400">Voor {{ $own['for'] }}, {{ count($p['tours']) }} rondleidingen</p>
                </div>

                <div class="shrink-0 text-right">
                    <p class="text-xl font-bold text-white">{{ $p['percentage'] }}%</p>
                    <span class="mt-1 block h-1.5 w-28 overflow-hidden rounded-full bg-surface-700">
                        <span class="block h-full rounded-full bg-brand-500" style="width: {{ $p['percentage'] }}%"></span>
                    </span>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2 border-t border-surface-700 pt-4">
                @foreach ($p['tours'] as $id)
                    @php($state = $progress[$id] ?? ['done' => false, 'started' => false])
                    <a href="{{ route('academy.show', $id) }}"
                       class="flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold transition {{ $state['done'] ? 'border-green-500/30 bg-green-500/10 text-green-300' : 'border-surface-600 text-slate-200 hover:border-brand-500 hover:text-white' }}">
                        @if ($state['done'])
                            <x-icon name="check" class="h-3.5 w-3.5" />
                        @elseif ($state['started'])
                            <x-icon name="arrow-path" class="h-3.5 w-3.5" />
                        @endif
                        {{ $tours[$id]['title'] }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Alle rondleidingen per categorie --}}
    @foreach ($categories as $key => $category)
        @php($own = collect($tours)->filter(fn ($t) => $t['category'] === $key))
        @continue($own->isEmpty())

        <section class="mb-5">
            <h2 class="mb-3 flex items-center gap-2 text-sm font-bold text-white">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg {{ Academy::chipClasses($category['colour']) }}">
                    <x-icon :name="$category['icon']" class="h-4 w-4" />
                </span>
                {{ $category['label'] }}
            </h2>

            <div data-motion-fan class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($own as $id => $tour)
                    @php($state = $progress[$id] ?? ['done' => false, 'started' => false, 'step' => 0])
                    <a href="{{ route('academy.show', $id) }}"
                       class="motion-lift block overflow-hidden rounded-2xl border border-surface-700 bg-surface-800 transition hover:border-surface-600">
                        <span class="block h-[3px] w-full {{ $state['done'] ? 'bg-green-500' : ($state['started'] ? 'bg-amber-500' : 'bg-surface-600') }}"></span>

                        <span class="block p-4">
                            <span class="mb-2 flex items-start justify-between gap-2">
                                <b class="min-w-0 text-sm font-bold text-white">{{ $tour['title'] }}</b>
                                @if ($state['done'])
                                    <span class="shrink-0 rounded-full bg-green-500/15 px-2 py-0.5 text-[10px] font-bold text-green-300">Afgerond</span>
                                @elseif ($state['started'])
                                    <span class="shrink-0 rounded-full bg-amber-500/15 px-2 py-0.5 text-[10px] font-bold text-amber-300">
                                        Stap {{ $state['step'] + 1 }}
                                    </span>
                                @endif
                            </span>

                            <span class="block text-xs leading-relaxed text-slate-400">{{ $tour['summary'] }}</span>

                            <span class="mt-3 flex items-center gap-3 text-xs text-slate-500">
                                <span class="flex items-center gap-1"><x-icon name="clock" class="h-3.5 w-3.5" /> {{ $tour['minutes'] }} minuten</span>
                                <span>{{ count($tour['steps']) }} stappen</span>
                                <span>{{ $tour['level'] }}</span>
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- De afspraken --}}
        <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <h2 class="mb-1 text-sm font-bold text-white">Zo doen we het <span class="text-brand-500">hier</span></h2>
            <p class="mb-4 text-xs text-slate-500">De afspraken waar Renovion zich aan houdt.</p>

            <div class="space-y-2.5">
                @foreach ($rules as [$rule, $icon])
                    <div class="flex items-start gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-brand-500/15 text-brand-300">
                            <x-icon :name="$icon" class="h-4 w-4" />
                        </span>
                        <span class="pt-1 text-sm text-slate-200">{{ $rule }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Veelgestelde vragen --}}
        <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <h2 class="mb-4 text-sm font-bold text-white">Veelgestelde <span class="text-brand-500">vragen</span></h2>

            <div class="space-y-1">
                @foreach ($faq as [$question, $answer])
                    <div x-data="{ open: false }" class="border-b border-surface-700 last:border-b-0">
                        <button @click="open = !open" class="flex w-full items-center justify-between gap-3 py-3 text-left">
                            <span class="text-sm font-semibold text-slate-200">{{ $question }}</span>
                            <x-icon name="chevron-down" class="h-4 w-4 shrink-0 text-slate-500" ::class="open ? 'rotate-180' : ''" />
                        </button>
                        <p x-show="open" x-cloak class="pb-3 text-sm text-slate-400">{{ $answer }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.app>
