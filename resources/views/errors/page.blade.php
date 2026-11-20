{{-- Het vangnet per pagina: het menu blijft werken, de pagina zelf legt uit wat er aan de hand is --}}
<x-layouts.app :title="$heading">
    <div class="mx-auto flex max-w-lg flex-col items-center py-12 text-center lg:py-20">
        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-brand-500/15 text-brand-400">
            <x-icon name="exclamation-triangle" class="h-7 w-7" />
        </span>

        <p class="mt-5 text-[10.5px] font-semibold tracking-[0.1em] text-slate-500 uppercase">Foutcode {{ $status }}</p>
        <h1 class="mt-1 text-2xl font-bold text-white">{{ $heading }}</h1>
        <p class="mt-2 text-sm text-slate-400">{{ $explanation }}</p>

        <div class="mt-6 flex flex-wrap justify-center gap-2">
            @if ($retry)
                <a href="{{ url()->current() }}" class="rounded-full bg-brand-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">Opnieuw proberen</a>
            @endif
            <a href="{{ route('dashboard') }}" class="rounded-full border border-surface-600 px-5 py-2.5 text-sm font-semibold text-slate-200 transition hover:text-white">Naar Vandaag</a>
        </div>

        <p class="mt-6 text-xs text-slate-500">Blijft het misgaan? Geef door op welk scherm je was en hoe laat: {{ now()->translatedFormat('j F H:i') }}.</p>
    </div>
</x-layouts.app>
