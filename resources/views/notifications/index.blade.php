<x-layouts.app title="Meldingen">

    <x-page-header eyebrow="Meldingen" title="Meldingen" subtitle="Wat je als pushmelding hebt gekregen, nieuwste bovenaan" />

    @if ($notifications->isEmpty())
        <x-empty-state title="Nog geen meldingen" subtitle="Vermeldingen, taken en ondertekende offertes verschijnen hier." icon="bell" />
    @else
        <div class="app-card">
            <div class="app-card-b">
                @foreach ($notifications as $notification)
                    <a href="{{ $notification->data['url'] ?? route('dashboard') }}" class="app-li">
                        <span class="app-ic {{ $notification->read_at ? 'blue' : 'or' }}"><x-icon :name="$notification->data['icon'] ?? 'bell'" class="h-[18px] w-[18px]" /></span>
                        <span class="min-w-0 flex-1">
                            <b>{{ $notification->data['title'] ?? 'Melding' }}</b>
                            @if ($notification->data['body'] ?? null)
                                <small>{{ $notification->data['body'] }}</small>
                            @endif
                            <small>{{ $notification->created_at->translatedFormat('j M, H:i') }}</small>
                        </span>
                        @unless ($notification->read_at)
                            <x-signal-dot color="amber" class="mt-1.5" title="Nieuw" />
                        @endunless
                    </a>
                @endforeach
            </div>
        </div>
    @endif

</x-layouts.app>
