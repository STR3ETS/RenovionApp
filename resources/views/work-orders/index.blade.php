@php
    use App\Enums\WorkOrderStatus;
@endphp

<x-layouts.app title="Werkbonnen">
    <x-page-header title="Werkbonnen" eyebrow="Uitvoering" category="project"
                   subtitle="Wat er op de bouwplaats gedaan is, in uren en materiaal. Na goedkeuring tellen de uren mee.">
        @can('manage-crm')
            <a href="{{ route('work-orders.index', ['status' => WorkOrderStatus::Controle->value]) }}"
               class="flex items-center gap-1.5 rounded-full bg-brand-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
                <x-icon name="clipboard-check" class="h-4 w-4" />
                {{ $counts[WorkOrderStatus::Controle->value] ?? 0 }} ter controle
            </a>
        @endcan
    </x-page-header>

    {{-- Filterpillen per status --}}
    <x-tabs>
        <x-tab :href="route('work-orders.index', ['project' => $projectFilter ?: null])"
               :active="$filter === ''" :count="$counts->sum()">Alles</x-tab>
        @foreach (WorkOrderStatus::filters() as $status)
            @continue(($counts[$status->value] ?? 0) === 0 && $filter !== $status->value)
            <x-tab :href="route('work-orders.index', ['status' => $status->value, 'project' => $projectFilter ?: null])"
                   :active="$filter === $status->value" :count="$counts[$status->value] ?? 0">
                {{ $status->label() }}
            </x-tab>
        @endforeach
    </x-tabs>

    @if ($orders->isEmpty())
        <x-empty-state icon="wrench" category="project" title="Geen werkbonnen"
                       subtitle="Zodra een uitvoerder een klus afrondt, staat de werkbon hier ter controle." />
    @else
        <div class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
            @foreach ($orders as $order)
                @php($afwijking = $order->hoursVariance())
                <a href="{{ route('work-orders.show', $order) }}"
                   class="flex items-center gap-3 border-b border-surface-700 border-l-4 p-4 transition last:border-b-0 hover:bg-white/5 {{ $order->status === \App\Enums\WorkOrderStatus::Controle ? 'border-l-amber-400' : 'border-l-transparent' }}">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-project-500/15 text-project-400">
                        <x-icon name="wrench" class="h-5 w-5" />
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <b class="truncate text-sm font-semibold text-white">{{ $order->title }}</b>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $order->status->badgeClasses() }}">{{ $order->status->label() }}</span>
                        </span>
                        <small class="mt-0.5 block truncate text-xs text-slate-400">
                            {{ $order->number }}, {{ $order->project->name }}, {{ $order->user?->name ?? 'niet toegewezen' }},
                            {{ $order->date->translatedFormat('j M') }}
                        </small>
                    </span>

                    <span class="shrink-0 text-right">
                        <b class="block text-sm font-bold text-white">{{ rtrim(rtrim(number_format((float) $order->hours, 2, ',', '.'), '0'), ',') }} uur</b>
                        @if ($afwijking !== null && abs($afwijking) >= 0.5)
                            <small class="block text-xs font-semibold {{ $afwijking > 0 ? 'text-red-400' : 'text-green-400' }}">
                                {{ $afwijking > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($afwijking, 1, ',', '.'), '0'), ',') }} tegenover begroot
                            </small>
                        @elseif ((float) $order->budget_hours > 0)
                            <small class="block text-xs text-slate-500">op begroting</small>
                        @endif
                    </span>
                </a>
            @endforeach
        </div>

        <div class="mt-4">{{ $orders->links() }}</div>
    @endif
</x-layouts.app>
