@php
    use App\Support\Academy;
    $current = $tour['steps'][$step];
    $category = $categories[$tour['category']];
    $last = $step === count($tour['steps']) - 1;
@endphp

<x-layouts.app :title="$tour['title']">
    <div class="mb-4">
        <a href="{{ route('academy.index') }}" class="flex items-center gap-1 text-sm font-semibold text-slate-400 transition hover:text-white">
            <x-icon name="chevron-left" class="h-4 w-4" /> Academy
        </a>
    </div>

    <x-page-header :title="$tour['title']" :eyebrow="$category['label']" category="calc"
                   :subtitle="$tour['summary']">
        <span class="rounded-full bg-calc-500/15 px-3 py-1.5 text-xs font-bold text-calc-300">
            Stap {{ $step + 1 }} van {{ count($tour['steps']) }}
        </span>
    </x-page-header>

    {{-- De stappen als balkjes, klikbaar --}}
    <nav class="mb-5 flex gap-1">
        @foreach ($tour['steps'] as $index => $s)
            <a href="{{ route('academy.show', [$id, 'stap' => $index]) }}"
               class="h-1.5 flex-1 rounded-full transition {{ $index <= $step ? 'bg-calc-500' : 'bg-surface-700 hover:bg-surface-600' }}"
               title="{{ $s['title'] }}"></a>
        @endforeach
    </nav>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <article class="rounded-2xl border border-surface-700 bg-surface-800 p-6 lg:p-8">
                <p class="text-[11px] font-bold tracking-widest text-calc-400 uppercase">Stap {{ $step + 1 }}</p>
                <h1 class="mt-2 text-xl font-bold text-white lg:text-2xl">{{ $current['title'] }}</h1>
                <p class="mt-4 text-base leading-relaxed text-slate-300">{{ $current['body'] }}</p>

                @if (isset($current['route']))
                    <a href="{{ route($current['route']) }}" target="_blank" rel="noopener"
                       class="mt-5 inline-flex items-center gap-1.5 rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
                        <x-icon name="arrow-right" class="h-4 w-4" /> Open dit scherm
                    </a>
                    <p class="mt-2 text-xs text-slate-500">Opent in een nieuw tabblad, zodat je deze uitleg erbij houdt.</p>
                @endif
            </article>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                @if ($step > 0)
                    <a href="{{ route('academy.show', [$id, 'stap' => $step - 1]) }}"
                       class="flex items-center gap-1.5 rounded-full border border-surface-600 px-4 py-2.5 text-sm font-semibold text-slate-300 transition hover:text-white">
                        <x-icon name="chevron-left" class="h-4 w-4" /> Vorige
                    </a>
                @endif

                @if (! $last)
                    <a href="{{ route('academy.show', [$id, 'stap' => $step + 1]) }}"
                       class="flex items-center gap-1.5 rounded-full bg-brand-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
                        Volgende <x-icon name="chevron-right" class="h-4 w-4" />
                    </a>
                @else
                    <form method="POST" action="{{ route('academy.complete', $id) }}">
                        @csrf
                        <button type="submit" class="flex items-center gap-1.5 rounded-full bg-brand-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
                            <x-icon name="check" class="h-4 w-4" /> Rondleiding afronden
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Alle stappen in deze rondleiding --}}
        <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <h2 class="mb-3 text-sm font-bold text-white">In deze <span class="text-brand-500">rondleiding</span></h2>

            <ol class="space-y-1">
                @foreach ($tour['steps'] as $index => $s)
                    <li>
                        <a href="{{ route('academy.show', [$id, 'stap' => $index]) }}"
                           class="flex items-start gap-2.5 rounded-lg px-2 py-2 transition {{ $index === $step ? 'bg-white/5' : 'hover:bg-white/5' }}">
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold {{ $index < $step ? 'bg-green-500/20 text-green-300' : ($index === $step ? 'bg-calc-500 text-white' : 'bg-surface-700 text-slate-500') }}">
                                {{ $index + 1 }}
                            </span>
                            <span class="min-w-0 text-sm {{ $index === $step ? 'font-semibold text-white' : 'text-slate-300' }}">{{ $s['title'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ol>

            @if ($progress->isComplete())
                <p class="mt-4 flex items-center gap-2 border-t border-surface-700 pt-4 text-sm text-green-400">
                    <x-icon name="check" class="h-4 w-4" />
                    Afgerond op {{ $progress->completed_at->translatedFormat('j M Y') }}
                </p>
            @endif
        </div>
    </div>
</x-layouts.app>
