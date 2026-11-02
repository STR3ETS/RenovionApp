{{-- De chronologische tijdlijn van het klantdossier: wie deed wat, wanneer en
     via welke bron. --}}
@if ($events->isEmpty())
    <x-empty-state icon="clock" category="klant" title="Nog niets vastgelegd"
                   subtitle="Elke belangrijke actie komt hier automatisch terecht." />
@else
    <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
        <ol>
            @foreach ($events as $event)
                <li class="relative flex gap-4 pb-5 last:pb-0">
                    @unless ($loop->last)
                        <span class="absolute top-8 bottom-0 left-[15px] w-px bg-surface-700"></span>
                    @endunless

                    <span class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-surface-750 text-slate-400">
                        <x-icon name="clock" class="h-4 w-4" />
                    </span>

                    <span class="min-w-0 flex-1 pt-1">
                        <span class="block text-sm font-semibold text-white">{{ $event->title }}</span>
                        @if ($event->body)
                            <span class="mt-0.5 block text-sm text-slate-400">{{ $event->body }}</span>
                        @endif
                        <span class="mt-0.5 block text-xs text-slate-500">
                            {{ $event->happened_at->translatedFormat('j M Y H:i') }},
                            {{ $event->type->label() }}, bron {{ $event->source->label() }}
                        </span>
                    </span>
                </li>
            @endforeach
        </ol>
    </div>
@endif
