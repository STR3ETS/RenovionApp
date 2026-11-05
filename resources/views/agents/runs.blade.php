@php
    use App\Models\AgentRun;
    use App\Support\AgentRegistry;
@endphp

<x-layouts.app title="Uitvoeringen">
    <x-page-header title="Uitvoeringen" eyebrow="Nova en agents" category="calc"
                   subtitle="Wat de agents doen, waar ze op wachten en wat het kost.">
        @if ($openApprovals > 0)
            <a href="{{ route('approvals.index') }}"
               class="flex items-center gap-1.5 rounded-full bg-brand-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
                <x-icon name="hand-thumb-up" class="h-4 w-4" />
                {{ $openApprovals }} {{ $openApprovals === 1 ? 'besluit' : 'besluiten' }} nodig
            </a>
        @endif
    </x-page-header>

    {{-- De acht agents met hun specialisme --}}
    <section data-motion-fan class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ($agents as $id => $agent)
            @php($own = $runs->getCollection()->where('agent', $id)->count())
            <a href="{{ route('agents.index', ['agent' => $agentFilter === $id ? null : $id, 'status' => $filter]) }}"
               class="motion-lift block rounded-2xl border p-4 transition {{ $agentFilter === $id ? 'border-brand-500 bg-brand-500/5' : 'border-surface-700 bg-surface-800 hover:border-surface-600' }}">
                <span class="mb-2 flex items-center gap-2">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ AgentRegistry::chipClasses($agent['colour']) }}">
                        {{ str($agent['name'])->substr(0, 1) }}
                    </span>
                    <b class="truncate text-sm font-bold text-white">{{ $agent['name'] }}</b>
                </span>
                <span class="block text-xs font-semibold text-slate-300">{{ $agent['field'] }}</span>
                <span class="mt-1 block text-[11px] leading-snug text-slate-500">{{ $agent['task'] }}</span>
            </a>
        @endforeach
    </section>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <x-tabs class="mb-0">
            @foreach (['actief' => 'Actief', 'alles' => 'Alles', AgentRun::STATUS_WAITING_APPROVAL => 'Wacht op goedkeuring', AgentRun::STATUS_DONE => 'Afgerond', AgentRun::STATUS_FAILED => 'Mislukt'] as $key => $label)
                @continue(($counts[$key] ?? 0) === 0 && $filter !== $key && ! in_array($key, ['actief', 'alles'], true))
                <x-tab :href="route('agents.index', ['status' => $key, 'agent' => $agentFilter ?: null])"
                       :active="$filter === $key" :count="$counts[$key] ?? 0">{{ $label }}</x-tab>
            @endforeach
        </x-tabs>

        <span class="flex shrink-0 items-center gap-1.5 rounded-full border border-surface-700 px-3.5 py-2 text-sm text-slate-400">
            Modelkosten <b class="text-white">EUR {{ number_format($cost, 2, ',', '.') }}</b>
        </span>
    </div>

    @if ($runs->isEmpty())
        <x-empty-state icon="sparkles" category="calc" title="Nog geen uitvoeringen"
                       subtitle="Zodra een gebeurtenis een skill in gang zet, staat de uitvoering hier met het hele verloop." />
    @else
        <div class="space-y-2">
            @foreach ($runs as $run)
                @php($agent = $run->agentMeta())
                <div x-data="{ open: false }" class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
                    <button @click="open = !open" class="flex w-full flex-wrap items-center gap-3 p-4 text-left transition hover:bg-white/5">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ AgentRegistry::chipClasses($agent['colour']) }}">
                            {{ str($agent['name'])->substr(0, 1) }}
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-2">
                                <b class="truncate text-sm font-semibold text-white">{{ $run->skillName() }}</b>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $run->badgeClasses() }}">{{ $run->statusLabel() }}</span>
                            </span>
                            <small class="mt-0.5 block truncate text-xs text-slate-400">
                                {{ $run->reference }}, {{ $agent['name'] }}
                                @if ($run->project), {{ $run->project->name }} @endif
                                @if ($run->trigger), aanleiding {{ $run->trigger }} @endif
                                , {{ $run->created_at->diffForHumans() }}
                            </small>
                        </span>

                        <span class="shrink-0 text-right">
                            @if ($run->duration() !== null)
                                <small class="block text-xs text-slate-500">{{ $run->duration() }} seconden</small>
                            @endif
                            @if ((float) $run->cost > 0)
                                <small class="block text-xs text-slate-500">EUR {{ number_format((float) $run->cost, 2, ',', '.') }}</small>
                            @endif
                        </span>
                    </button>

                    <div x-show="open" x-cloak class="border-t border-surface-700 bg-surface-850 p-4">
                        <div class="grid gap-4 lg:grid-cols-2">
                            <div>
                                <p class="mb-2 text-[11px] font-bold tracking-widest text-slate-500 uppercase">Verloop</p>
                                @if (filled($run->steps))
                                    <ol class="space-y-1.5">
                                        @foreach ($run->steps as $step)
                                            <li class="flex items-start gap-2 text-sm">
                                                <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-brand-500"></span>
                                                <span class="min-w-0">
                                                    <span class="block text-slate-200">{{ $step['description'] }}</span>
                                                    <span class="block text-xs text-slate-500">{{ $step['at'] }}</span>
                                                </span>
                                            </li>
                                        @endforeach
                                    </ol>
                                @else
                                    <p class="text-sm text-slate-500">Nog geen stappen vastgelegd.</p>
                                @endif
                            </div>

                            <div>
                                <p class="mb-2 text-[11px] font-bold tracking-widest text-slate-500 uppercase">Over deze skill</p>
                                @php($skill = AgentRegistry::skill($run->skill))
                                @if ($skill)
                                    <dl class="space-y-1.5 text-sm">
                                        <div><dt class="inline text-slate-400">Niveau:</dt> <dd class="inline text-slate-200">{{ AgentRegistry::LEVELS[$skill['level']]['label'] }}</dd></div>
                                        <div><dt class="inline text-slate-400">Beslist:</dt> <dd class="inline text-slate-200">{{ $skill['approver'] ?? 'niemand, dit is protocol' }}</dd></div>
                                        <div><dt class="inline text-slate-400">Invoer:</dt> <dd class="inline text-slate-200">{{ $skill['input'] }}</dd></div>
                                        <div><dt class="inline text-slate-400">Uitkomst:</dt> <dd class="inline text-slate-200">{{ $skill['output'] }}</dd></div>
                                    </dl>
                                @endif

                                @if ($run->note)
                                    <p class="mt-3 rounded-lg bg-surface-800 p-3 text-xs text-slate-300">{{ $run->note }}</p>
                                @endif

                                @if ($run->approvals->isNotEmpty())
                                    <div class="mt-3">
                                        <p class="mb-1.5 text-[11px] font-bold tracking-widest text-slate-500 uppercase">Besluiten</p>
                                        @foreach ($run->approvals as $approval)
                                            <div class="flex items-center gap-2 text-sm">
                                                <span class="min-w-0 flex-1 truncate text-slate-200">{{ $approval->summary }}</span>
                                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $approval->badgeClasses() }}">{{ $approval->statusLabel() }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $runs->links() }}</div>
    @endif
</x-layouts.app>
