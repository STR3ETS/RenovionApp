<x-layouts.app title="Audit">
    <x-page-header title="Audit" eyebrow="Nova en agents" category="calc"
                   subtitle="Wie deed wat, wanneer en waarover. De vraag die hier altijd te beantwoorden is: een agent, of een mens." />

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <select name="wie" onchange="this.form.submit()"
                class="rounded-full border-surface-700 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
            <option value="">Iedereen</option>
            <option value="agent" @selected($wie === 'agent')>Alleen agents</option>
            <option value="mens" @selected($wie === 'mens')>Alleen mensen</option>
        </select>

        <select name="type" onchange="this.form.submit()"
                class="rounded-full border-surface-700 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
            <option value="">Alle gebeurtenissen</option>
            @foreach ($types as $soort)
                <option value="{{ $soort }}" @selected($type === $soort)>{{ $soort }}</option>
            @endforeach
        </select>

        <select name="project" onchange="this.form.submit()"
                class="rounded-full border-surface-700 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
            <option value="">Alle projecten</option>
            @foreach ($projects as $project)
                <option value="{{ $project->id }}" @selected($projectFilter === $project->id)>{{ $project->name }}</option>
            @endforeach
        </select>
    </form>

    @if ($events->isEmpty())
        <x-empty-state icon="clipboard-document-list" category="calc" title="Nog geen gebeurtenissen"
                       subtitle="Zodra een agent of een mens iets doet, staat het hier onveranderlijk vast." />
    @else
        <div class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
            @foreach ($events as $event)
                <div class="flex flex-wrap items-start gap-3 border-b border-surface-700 border-l-4 p-4 last:border-b-0 {{ $event->isAgent() ? 'border-l-calc-500/60' : 'border-l-brand-500/60' }}">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $event->isAgent() ? 'bg-calc-500/15 text-calc-300' : 'bg-brand-500/15 text-brand-300' }}">
                        <x-icon :name="$event->isAgent() ? 'sparkles' : 'user'" class="h-4 w-4" />
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <b class="text-sm font-semibold text-white">{{ $event->actorName() }}</b>
                            <span class="rounded bg-surface-700 px-1.5 py-0.5 font-mono text-[10px] text-slate-400">{{ $event->type }}</span>
                            @unless ($event->isAgent())
                                <span class="rounded-full bg-brand-500/15 px-2 py-0.5 text-[10px] font-bold text-brand-300">Mens</span>
                            @endunless
                        </span>

                        @if (filled($event->payload))
                            <span class="mt-1 block text-xs text-slate-400">
                                @foreach ($event->payload as $key => $value)
                                    @continue($value === null)
                                    <span class="mr-3 inline-block">
                                        <span class="text-slate-500">{{ str_replace('_', ' ', (string) $key) }}:</span>
                                        {{ is_scalar($value) ? (is_bool($value) ? ($value ? 'ja' : 'nee') : $value) : json_encode($value) }}
                                    </span>
                                @endforeach
                            </span>
                        @endif

                        <span class="mt-0.5 block text-xs text-slate-500">
                            {{ $event->occurred_at->translatedFormat('j M Y H:i:s') }}
                            @if ($event->project), {{ $event->project->name }} @endif
                            @if ($event->run), {{ $event->run->reference }} @endif
                        </span>
                    </span>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $events->links() }}</div>
    @endif
</x-layouts.app>
