@props(['health', 'compact' => false])

{{-- De vier stoplichten van de projectcockpit (briefing hoofdstuk 7). --}}
@if ($compact)
    <div {{ $attributes->merge(['class' => 'flex items-center gap-1.5']) }}>
        @foreach ($health->signals as $signal)
            <span class="flex items-center gap-1" title="{{ $signal->label }}: {{ $signal->reason }}">
                <x-signal-dot :color="$signal->light->dotColor()" />
                <span class="text-[10px] font-semibold text-slate-500">{{ mb_substr($signal->label, 0, 1) }}</span>
            </span>
        @endforeach
    </div>
@else
    <div {{ $attributes->merge(['class' => 'grid gap-2 sm:grid-cols-2 xl:grid-cols-4']) }}>
        @foreach ($health->signals as $signal)
            <div class="rounded-2xl border p-4 {{ $signal->light->cardClasses() }}">
                <div class="flex items-center justify-between gap-2">
                    <span class="flex items-center gap-2 text-xs font-bold {{ $signal->light->textClasses() }}">
                        <x-icon :name="$signal->icon" class="h-4 w-4" />
                        {{ $signal->label }}
                    </span>
                    <x-signal-dot :color="$signal->light->dotColor()" class="h-2.5 w-2.5" />
                </div>
                <p class="mt-1.5 text-sm font-bold text-white">{{ $signal->light->label() }}</p>
                <p class="mt-0.5 text-xs leading-relaxed text-slate-300">{{ $signal->reason }}</p>
            </div>
        @endforeach
    </div>
@endif
