<x-layouts.app title="Automations">

    <x-page-header eyebrow="Nova" title="Automations" subtitle="Per regel instelbaar: alleen signaleren, eerst bevestigen of automatisch uitvoeren. Draait elke 15 minuten." />

    <div class="space-y-2">
        @foreach ($automations as $automation)
            <div class="flex flex-col gap-3 rounded-xl border bg-white p-4 lg:flex-row lg:items-center {{ $automation['mode'] === \App\Enums\AutomationMode::Uit ? 'border-gray-100 opacity-60' : 'border-gray-200' }}">
                <div class="flex min-w-0 flex-1 items-start gap-3 lg:items-center">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $automation['mode'] === \App\Enums\AutomationMode::Automatisch ? 'bg-green-100 text-green-700' : 'bg-navy-100 text-navy-700' }}">
                        <x-icon name="bolt" class="h-4.5 w-4.5" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold text-navy-900">{{ $automation['name'] }}</span>
                        <span class="block text-xs text-gray-500">{{ $automation['description'] }}</span>
                        <span class="mt-0.5 block text-[11px] text-gray-500">{{ $automation['mode']->description() }}</span>
                    </span>
                </div>
                <div class="flex items-center justify-between gap-3 sm:pl-12 lg:shrink-0 lg:pl-0 app:flex-col app:items-stretch app:gap-2">
                    <span class="text-xs whitespace-nowrap text-gray-500 lg:text-right">
                        {{ $automation['runs'] }}× uitgevoerd
                        @if ($automation['last_run_at'])
                            <span class="block">laatst {{ $automation['last_run_at']->translatedFormat('j M H:i') }}</span>
                        @endif
                    </span>
                    @can('manage-team')
                        <form method="POST" action="{{ route('automations.update', $automation['key']) }}" class="min-w-0 app:w-full">
                            @csrf @method('PATCH')
                            <select name="mode" onchange="this.form.submit()" aria-label="Modus voor {{ $automation['name'] }}" class="max-w-full rounded-lg border-gray-200 py-1.5 text-xs font-semibold focus:border-brand-500 focus:ring-brand-500 pointer-coarse:py-2.5 app:w-full">
                                @foreach (\App\Enums\AutomationMode::cases() as $mode)
                                    <option value="{{ $mode->value }}" @selected($automation['mode'] === $mode)>{{ $mode->label() }}</option>
                                @endforeach
                            </select>
                        </form>
                    @else
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $automation['mode']->badgeClasses() }}">{{ $automation['mode']->label() }}</span>
                    @endcan
                </div>
            </div>
        @endforeach

        {{-- In code ingebouwde automations --}}
        <div class="flex flex-wrap items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 opacity-80">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-700">
                <x-icon name="check" class="h-4.5 w-4.5" />
            </span>
            <span class="min-w-0 flex-[1_1_12rem]">
                <span class="block text-sm font-semibold text-navy-900">Offerte akkoord → project aanmaken</span>
                <span class="block text-xs text-gray-500">Ingebouwd in de offerteflow: bij akkoord wordt automatisch een project aangemaakt en springt de lead naar "Project".</span>
            </span>
            <span class="text-xs whitespace-nowrap text-gray-500">altijd actief</span>
        </div>
    </div>

    <p class="mt-4 text-xs text-gray-500">
        Bij "signaleren" en "eerst bevestigen" meldt Nova zich in het teamkanaal Algemeen. Automations voor WhatsApp en reviewflow volgen later.
    </p>

</x-layouts.app>
