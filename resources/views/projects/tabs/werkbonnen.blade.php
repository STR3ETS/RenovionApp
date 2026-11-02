@php
    use App\Enums\WorkOrderStatus;
    $num = fn (float $v) => rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
@endphp

{{-- De werkbonnen van dit project. Pas na goedkeuring tellen de uren mee in de
     kosten en de voortgang. --}}
@if ($workOrders->isEmpty())
    <x-empty-state icon="wrench" category="project" title="Nog geen werkbonnen"
                   subtitle="Zodra een uitvoerder zijn klus afrondt, komt de bon hier ter controle." />
@else
    <div class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
        @foreach ($workOrders as $order)
            @php($afwijking = $order->hoursVariance())
            <a href="{{ route('work-orders.show', $order) }}"
               class="flex flex-wrap items-center gap-3 border-b border-surface-700 border-l-4 p-4 transition last:border-b-0 hover:bg-white/5 {{ $order->status === WorkOrderStatus::Controle ? 'border-l-amber-400' : 'border-l-transparent' }}">
                <span class="min-w-0 flex-1">
                    <span class="flex flex-wrap items-center gap-2">
                        <b class="truncate text-sm font-semibold text-white">{{ $order->title }}</b>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $order->status->badgeClasses() }}">{{ $order->status->label() }}</span>
                    </span>
                    <span class="mt-0.5 block truncate text-xs text-slate-400">
                        {{ $order->number }}, {{ $order->user?->name ?? 'niet toegewezen' }}, {{ $order->date->translatedFormat('j M Y') }}
                        @if ($order->isSigned()), afgetekend door {{ $order->signed_name }} @endif
                    </span>
                </span>

                <span class="shrink-0 text-right">
                    <b class="block text-sm font-bold text-white">{{ $num((float) $order->hours) }} uur</b>
                    @if ($afwijking !== null && abs($afwijking) >= 0.5)
                        <small class="block text-xs font-semibold {{ $afwijking > 0 ? 'text-red-400' : 'text-green-400' }}">
                            {{ $afwijking > 0 ? '+' : '' }}{{ $num($afwijking) }} tegenover begroot
                        </small>
                    @elseif ((float) $order->budget_hours > 0)
                        <small class="block text-xs text-slate-500">op begroting</small>
                    @endif
                </span>
            </a>
        @endforeach
    </div>
@endif
