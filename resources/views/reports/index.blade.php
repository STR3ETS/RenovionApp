<x-layouts.app title="Rapportages">
    <x-page-header title="Rapportages" eyebrow="Inzicht" category="offerte"
                   subtitle="Een rapport is een momentopname uit de data die er is, dus altijd actueel.">
        <button onclick="window.print()" class="flex items-center gap-1.5 rounded-full border border-surface-600 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:text-white">
            <x-icon name="document-text" class="h-4 w-4" /> Afdrukken
        </button>
    </x-page-header>

    <div class="scrollbar-none mb-4 flex gap-2 overflow-x-auto print:hidden">
        @foreach ($types as $key => $meta)
            <a href="{{ route('reports.index', ['type' => $key, 'project' => $projectFilter]) }}"
               class="flex shrink-0 items-center gap-2 rounded-xl border px-4 py-3 transition {{ $type === $key ? 'border-brand-500 bg-brand-500/10' : 'border-surface-700 bg-surface-800 hover:border-surface-600' }}">
                <x-icon :name="$meta['icon']" class="h-4 w-4 {{ $type === $key ? 'text-brand-400' : 'text-slate-500' }}" />
                <span class="text-left">
                    <span class="block text-sm font-bold {{ $type === $key ? 'text-white' : 'text-slate-300' }}">{{ $meta['label'] }}</span>
                    <span class="block text-[11px] text-slate-500">{{ $meta['about'] }}</span>
                </span>
            </a>
        @endforeach
    </div>

    @if ($type === 'project')
        <form method="GET" class="mb-4 print:hidden">
            <input type="hidden" name="type" value="project">
            <select name="project" onchange="this.form.submit()"
                    class="rounded-full border-surface-700 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                <option value="">Kies een project</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected($projectFilter === $project->id)>
                        {{ $project->name }}{{ $project->customer ? ', '.$project->customer->name : '' }}
                    </option>
                @endforeach
            </select>
        </form>
    @endif

    <article class="rounded-2xl border border-surface-700 bg-surface-800 p-6 lg:p-8">
        <header class="mb-6 border-b border-surface-700 pb-5">
            <p class="text-[11px] font-bold tracking-widest text-offerte-400 uppercase">Renovion</p>
            <h1 class="mt-1 text-2xl font-bold text-white">{{ $report['title'] }}</h1>
            @if ($report['period'])
                <p class="mt-1 text-sm text-slate-400">{{ $report['period'] }}</p>
            @endif
        </header>

        @if ($report['kpis'] !== [])
            <section class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
                @foreach ($report['kpis'] as $kpi)
                    <div>
                        <p class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">{{ $kpi['label'] }}</p>
                        <p class="mt-1 text-xl font-bold {{ $kpi['bad'] ? 'text-red-400' : 'text-white' }}">{{ $kpi['value'] }}</p>
                    </div>
                @endforeach
            </section>
        @endif

        <div class="space-y-6">
            @foreach ($report['sections'] as $index => $section)
                <section>
                    <h2 class="mb-3 flex items-baseline gap-2 text-sm font-bold text-white">
                        <span class="font-mono text-xs text-slate-600">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        {{ $section['title'] }}
                    </h2>

                    @if ($section['lines'] === [])
                        <p class="text-sm text-slate-500">Niets te melden.</p>
                    @else
                        <ul class="space-y-1.5">
                            @foreach ($section['lines'] as $line)
                                <li class="flex items-start gap-2.5 text-sm">
                                    <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full {{ $line['bad'] ? 'bg-red-500' : 'bg-green-500' }}"></span>
                                    <span class="{{ $line['bad'] ? 'text-slate-200' : 'text-slate-300' }}">{{ $line['text'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        </div>

        <footer class="mt-8 border-t border-surface-700 pt-4 text-xs text-slate-500">
            Opgemaakt op {{ now()->translatedFormat('j F Y H:i') }} door {{ auth()->user()->name }}.
            Een rode stip betekent dat die regel aandacht vraagt.
        </footer>
    </article>
</x-layouts.app>
