{{-- Dag of week van één medewerker: uren van zeven tot zeven, met bovenaan wat geen tijd heeft --}}
@php
    $kolommen = $days->count();
    $perDag = $items->groupBy('date');
@endphp

<div class="tw rounded-2xl border border-surface-700 bg-surface-800">
    <div class="grid" style="grid-template-columns: 56px repeat({{ $kolommen }}, minmax({{ $kolommen > 1 ? '150px' : '0' }}, 1fr)); {{ $kolommen > 1 ? 'min-width: 820px' : '' }}">

        {{-- Kop: de dagen --}}
        <div class="border-b border-surface-700 bg-surface-850"></div>
        @foreach ($days as $day)
            <div class="border-b border-l border-surface-700 bg-surface-850 px-3 py-2.5 text-[10.5px] font-semibold tracking-[0.08em] uppercase {{ $day->isToday() ? 'text-brand-400' : 'text-faint' }}">
                {{ $day->translatedFormat('D j M') }}
            </div>
        @endforeach

        {{-- Zonder tijd, of buiten het raster --}}
        <div class="border-b border-surface-700 px-2 py-2 text-[10.5px] font-semibold text-faint">Dag</div>
        @foreach ($days as $day)
            @php($los = ($perDag[$day->toDateString()] ?? collect())->reject($onGrid))
            <div class="min-h-[44px] cursor-pointer space-y-1 border-b border-l border-surface-700 p-1.5 transition hover:bg-white/[0.03]"
                 @click.self="nieuw(@js($day->toDateString()), '', @js($subject->id))">
                @foreach ($los as $item)
                    @include('agenda.partials.item', ['item' => $item])
                @endforeach
            </div>
        @endforeach

        {{-- De uren --}}
        @foreach ($hours as $hour)
            <div class="border-b border-surface-700/60 px-2 py-2 text-right text-[11px] text-faint tabular-nums last:border-b-0">
                {{ sprintf('%02d:00', $hour) }}
            </div>
            @foreach ($days as $day)
                @php($inUur = ($perDag[$day->toDateString()] ?? collect())->filter($onGrid)->filter(fn ($item) => (int) substr($item['start'], 0, 2) === $hour))
                <div class="group min-h-[44px] cursor-pointer space-y-1 border-b border-l border-surface-700/60 p-1.5 transition hover:bg-white/[0.03] {{ $day->isToday() && now()->hour === $hour ? 'bg-brand-500/[0.06]' : '' }}"
                     @click.self="nieuw(@js($day->toDateString()), @js(sprintf('%02d:00', $hour)), @js($subject->id))">
                    @foreach ($inUur as $item)
                        @include('agenda.partials.item', ['item' => $item])
                    @endforeach
                    @if ($inUur->isEmpty())
                        <button type="button" @click="nieuw(@js($day->toDateString()), @js(sprintf('%02d:00', $hour)), @js($subject->id))"
                                class="sr-only focus:not-sr-only focus:rounded focus:bg-surface-700 focus:px-2 focus:py-1 focus:text-xs focus:text-ink">
                            Nieuw op {{ $day->translatedFormat('l j M') }} om {{ sprintf('%02d:00', $hour) }}
                        </button>
                    @endif
                </div>
            @endforeach
        @endforeach
    </div>
</div>

@if ($items->isEmpty())
    <p class="mt-3 text-sm text-faint">
        Nog niets gepland in deze periode. Klik op een leeg vak voor een afspraak, taak, terugbelverzoek of belpoging.
    </p>
@endif
