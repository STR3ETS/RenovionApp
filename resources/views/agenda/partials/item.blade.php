@php
    // Kleur per bron, zodat je in één blik ziet wat voor soort item het is.
    $tone = match ($item['kind']) {
        'afspraak' => 'border-lead-500/40 bg-lead-500/15 text-lead-100',
        'taak' => 'border-calc-500/40 bg-calc-500/15 text-calc-100',
        'bellen' => 'border-klant-500/40 bg-klant-500/15 text-klant-100',
        default => 'border-project-500/40 bg-project-500/15 text-project-100',
    };
    $tijd = $item['start'] ? $item['start'].($item['end'] ? ' tot '.$item['end'] : '') : null;
@endphp

<div class="rounded-lg border px-2 py-1.5 text-left text-xs {{ $tone }} {{ $item['done'] ? 'opacity-50' : '' }} {{ $item['late'] ? 'ring-1 ring-red-500/60' : '' }}">
    <div class="flex items-start justify-between gap-1.5">
        <div class="min-w-0">
            <p class="truncate font-semibold {{ $item['done'] ? 'line-through' : '' }}">
                @if ($item['url'] ?? null)
                    <a href="{{ $item['url'] }}" class="hover:underline">{{ $item['title'] }}</a>
                @else
                    {{ $item['title'] }}
                @endif
            </p>
            <p class="truncate text-[11px] opacity-80">
                {{ collect([$tijd, $item['label'], $item['sub'] ?: null])->filter()->join(', ') }}
            </p>
            @if ($item['late'])
                <p class="text-[11px] font-semibold text-red-300">Te laat</p>
            @endif
            @if ($item['advice'] ?? null)
                <p class="mt-0.5 text-[11px] font-semibold text-amber-200">Nova: {{ $item['advice'] }}</p>
            @endif
        </div>

        @if ($item['kind'] === 'bellen' && $canCrm)
            <button type="button" @click.stop="bel({{ $item['lead_id'] }})" title="Belpoging vastleggen"
                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-ink/10 transition hover:bg-ink/20" aria-label="Belpoging vastleggen voor {{ $item['title'] }}">
                <x-icon name="phone" class="h-3.5 w-3.5" />
            </button>
        @elseif ($item['kind'] === 'afspraak')
            <form method="POST" action="{{ route('agenda.done', $item['entry_id']) }}" class="shrink-0">
                @csrf
                <button type="submit" title="{{ $item['done'] ? 'Weer openzetten' : 'Afvinken' }}"
                        class="flex h-7 w-7 items-center justify-center rounded-full bg-ink/10 transition hover:bg-ink/20" aria-label="{{ $item['done'] ? 'Afspraak weer openzetten' : 'Afspraak afvinken' }}">
                    <x-icon name="check" class="h-3.5 w-3.5" />
                </button>
            </form>
        @endif
    </div>
</div>
