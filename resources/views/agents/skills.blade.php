@php
    use App\Support\AgentRegistry;
@endphp

<x-layouts.app title="Skills en SOPs">
    <x-page-header title="Skills en SOPs" eyebrow="Nova en agents" category="calc"
                   subtitle="Wat mag welke agent, op welk niveau, en wie beslist erover." />

    {{-- De vier niveaus --}}
    <section class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($levels as $key => $level)
            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-4">
                <p class="text-[11px] font-bold tracking-widest text-calc-400 uppercase">{{ $level['label'] }}</p>
                <p class="mt-1 text-sm text-slate-300">{{ $level['about'] }}</p>
                <p class="mt-2 text-xs text-slate-500">
                    {{ collect($settings)->where('level', $key)->count() }} van {{ count($skills) }} skills
                </p>
            </div>
        @endforeach
    </section>

    {{-- De standaardprocedures: wat gebeurt er na welke gebeurtenis --}}
    <div class="mb-5 rounded-2xl border border-surface-700 bg-surface-800 p-5">
        <h2 class="mb-1 text-sm font-bold text-white">Standaard<span class="text-brand-500">procedures</span></h2>
        <p class="mb-4 text-xs text-slate-500">Welke gebeurtenis welke skills in gang zet.</p>

        <div class="space-y-2">
            @foreach ($sops as $event => $sop)
                <div class="flex flex-wrap items-center gap-3 rounded-xl border border-surface-700 bg-surface-850 p-3">
                    <span class="shrink-0 rounded bg-surface-700 px-2 py-1 font-mono text-[10px] text-slate-400">{{ $event }}</span>
                    <b class="shrink-0 text-sm font-semibold text-white">{{ $sop['label'] }}</b>
                    <x-icon name="arrow-right" class="h-3.5 w-3.5 shrink-0 text-slate-600" />
                    <span class="flex min-w-0 flex-wrap gap-1.5">
                        @foreach ($sop['skills'] as $skillId)
                            @php($agent = AgentRegistry::agent($skills[$skillId]['agent']))
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ AgentRegistry::chipClasses($agent['colour']) }}">
                                {{ $agent['name'] }}: {{ $skills[$skillId]['name'] }}
                            </span>
                        @endforeach
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- De skills per agent --}}
    @foreach ($agents as $agentId => $agent)
        @php($own = collect($skills)->filter(fn ($s) => $s['agent'] === $agentId))
        @continue($own->isEmpty())

        <div class="mb-4 overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
            <div class="flex flex-wrap items-center gap-3 border-b border-surface-700 p-5">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ AgentRegistry::chipClasses($agent['colour']) }}">
                    {{ str($agent['name'])->substr(0, 1) }}
                </span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-sm font-bold text-white">{{ $agent['name'] }}, <span class="text-brand-500">{{ $agent['field'] }}</span></h2>
                    <p class="mt-0.5 text-xs text-slate-400">{{ $agent['task'] }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">Menselijke eigenaar: {{ $agent['owner'] }}</p>
                </div>
            </div>

            <div class="divide-y divide-surface-700">
                @foreach ($own as $skillId => $skill)
                    @php($setting = $settings[$skillId])
                    <div class="p-4">
                        <div class="flex flex-wrap items-start gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <b class="text-sm font-semibold text-white">{{ $skill['name'] }}</b>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $setting['paused'] ? 'bg-white/10 text-slate-400' : 'bg-calc-500/15 text-calc-300' }}">
                                        {{ $levels[$setting['level']]['label'] }}
                                    </span>
                                    @if ($setting['paused'])
                                        <span class="rounded-full bg-red-500/15 px-2 py-0.5 text-[10px] font-bold text-red-300">Op pauze</span>
                                    @endif
                                    @if ($setting['overridden'])
                                        <span class="rounded-full bg-amber-500/15 px-2 py-0.5 text-[10px] font-bold text-amber-300" title="Afwijkend van de standaard">Aangepast</span>
                                    @endif
                                </div>

                                <p class="mt-1 text-xs text-slate-500">
                                    Invoer: {{ $skill['input'] }}. Uitkomst: {{ $skill['output'] }}.
                                </p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    Beslist: {{ $skill['approver'] ?? 'niemand, dit is een vastgestelde actie' }}
                                    @if ($setting['limit'] !== null)
                                        , boven EUR {{ number_format($setting['limit'], 0, ',', '.') }} altijd een mens
                                    @endif
                                    @if (($runCounts[$skillId] ?? 0) > 0)
                                        , {{ $runCounts[$skillId] }} {{ $runCounts[$skillId] === 1 ? 'uitvoering' : 'uitvoeringen' }}
                                    @endif
                                </p>
                            </div>
                        </div>

                        @can('manage-team')
                            <form method="POST" action="{{ route('skills.update', $skillId) }}" class="mt-3 flex flex-wrap items-center gap-2 border-t border-surface-700 pt-3">
                                @csrf
                                @method('PATCH')

                                <select name="level" class="shrink-0 rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                                    @foreach ($levels as $key => $level)
                                        <option value="{{ $key }}" @selected($setting['level'] === $key)>{{ $level['label'] }}</option>
                                    @endforeach
                                </select>

                                <input type="number" step="0.01" min="0" name="limit_amount" value="{{ $setting['limit'] }}"
                                       placeholder="Bedragsgrens"
                                       class="w-36 shrink-0 rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">

                                <label class="flex shrink-0 items-center gap-2 text-sm text-slate-300">
                                    <input type="checkbox" name="paused" value="1" @checked($setting['paused'])
                                           class="rounded border-surface-600 bg-surface-750 text-red-500 focus:ring-red-500">
                                    Pauze
                                </label>

                                <button type="submit" class="ml-auto shrink-0 rounded-xl bg-brand-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-600">
                                    Opslaan
                                </button>
                            </form>
                        @endcan
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</x-layouts.app>
