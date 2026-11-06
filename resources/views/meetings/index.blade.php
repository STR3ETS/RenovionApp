<x-layouts.app title="Gespreksverslagen">

    <x-page-header eyebrow="Nova notuleert" category="planning" title="Gespreksverslagen" subtitle="{{ \App\Support\Tally::of($meetings->total(), 'verslag', 'verslagen') }}">
        <a href="{{ route('meetings.create') }}" class="flex items-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-600 active:bg-brand-600">
            <x-icon name="microphone" class="h-4 w-4" /> Gesprek opnemen
        </a>
    </x-page-header>

    <div class="space-y-2">
        @forelse ($meetings as $meeting)
            <a href="{{ route('meetings.show', $meeting) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 border-l-4 border-l-planning-500 bg-white p-3 transition hover:shadow-sm">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-planning-100 text-planning-700"><x-icon name="microphone" class="h-4.5 w-4.5" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-navy-900">{{ $meeting->title }}</span>
                    <span class="block truncate text-xs text-gray-500">
                        {{ collect([$meeting->kind->label(), $meeting->project?->name, $meeting->started_at->translatedFormat('j M Y H:i'), $meeting->duration_seconds ? gmdate($meeting->duration_seconds >= 3600 ? 'G:i:s' : 'i:s', $meeting->duration_seconds) : null])->filter()->implode(', ') }}
                    </span>
                </span>
                <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap {{ $meeting->status->badgeClasses() }}">{{ $meeting->status === \App\Enums\MeetingStatus::Verwerken ? 'Bezig' : $meeting->status->label() }}</span>
            </a>
        @empty
            <x-empty-state title="Nog geen gespreksverslagen" subtitle="Neem een overleg, bouwvergadering of klantgesprek op. Nova maakt er een verslag van, met besluiten, acties en open vragen." icon="microphone" category="planning">
                <a href="{{ route('meetings.create') }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-bold text-white">Gesprek opnemen</a>
            </x-empty-state>
        @endforelse
    </div>

    <div class="mt-4">{{ $meetings->links() }}</div>

</x-layouts.app>
