@php
    $raam = $subcontractor->activeFramework();
@endphp

{{-- Doorlopend werk: een raamovereenkomst van zes maanden die iemand bewust verlengt --}}
<div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
    <h2 class="mb-1 text-sm font-bold text-white">Raam<span class="text-brand-500">overeenkomst</span></h2>
    <p class="mb-3 text-xs text-slate-500">Voor doorlopend werk. Loopt zes maanden en verlengt nooit vanzelf: een nieuwe periode leg je hier vast.</p>

    @if ($raam)
        <p class="text-sm {{ $raam->daysLeft() <= 31 ? 'font-semibold text-amber-300' : 'text-green-300' }}">
            Loopt van {{ $raam->starts_on->translatedFormat('j M Y') }} t/m {{ $raam->ends_on->translatedFormat('j M Y') }},
            nog {{ $raam->daysLeft() }} {{ $raam->daysLeft() === 1 ? 'dag' : 'dagen' }}.
        </p>
    @else
        <p class="text-sm text-slate-400">Geen lopende raamovereenkomst.</p>
    @endif

    @if ($subcontractor->frameworks->count() > ($raam ? 1 : 0))
        <ul class="mt-2 space-y-1 text-xs text-slate-500">
            @foreach ($subcontractor->frameworks as $framework)
                @continue($raam && $framework->is($raam))
                <li>{{ $framework->starts_on->translatedFormat('j M Y') }} t/m {{ $framework->ends_on->translatedFormat('j M Y') }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('subcontractor-frameworks.store', $subcontractor) }}" class="mt-3"
          onsubmit="return confirm('Een nieuwe periode van zes maanden vastleggen?')">
        @csrf
        <button type="submit" class="w-full rounded-full border border-surface-600 py-2 text-sm font-semibold text-slate-200 transition hover:border-brand-500 hover:text-white">
            {{ $raam ? 'Volgende periode vastleggen' : 'Periode van zes maanden vastleggen' }}
        </button>
    </form>
</div>

{{-- Het logboek: gegund, niet gegund, vastgelegd --}}
<div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
    <h2 class="mb-3 text-sm font-bold text-white">Log<span class="text-brand-500">boek</span></h2>

    @forelse ($subcontractor->logs->take(20) as $log)
        <div class="border-b border-surface-700 py-2 text-sm last:border-b-0">
            <p class="text-slate-200">{{ $log->body }}</p>
            <p class="mt-0.5 text-xs text-slate-500">
                {{ collect([$log->logged_at->translatedFormat('j M Y'), $log->project?->name, $log->user?->name])->filter()->join(', ') }}
            </p>
        </div>
    @empty
        <p class="text-sm text-slate-500">Nog niets vastgelegd over deze partij.</p>
    @endforelse
</div>
