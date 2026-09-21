@props(['lead'])

{{-- Leadcard volgens briefing: alleen wat nodig is om een beslissing te nemen. --}}
<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white p-3 shadow-sm transition hover:border-brand-300']) }}>
    <a href="{{ route('leads.show', $lead) }}" class="block">
        <p class="font-semibold text-navy-900">{{ $lead->customer->name }}</p>
        <p class="text-xs text-gray-500">{{ $lead->customer->city ?? 'Plaats onbekend' }}</p>
        @if ($lead->service)
            <p class="mt-1 text-sm text-gray-700">{{ $lead->service }}</p>
        @endif
        @if ($lead->value)
            <p class="mt-1 text-sm font-bold text-navy-900">€ {{ number_format((float) $lead->value, 0, ',', '.') }}</p>
        @endif

        <div class="mt-2 space-y-1">
            @if ($lead->missingPhone())
                <p class="flex items-center gap-1.5 text-xs font-medium text-amber-600"><x-signal-dot color="amber" /> Geen telefoonnummer</p>
            @endif
            @if ($lead->next_action_at?->isPast() && ! $lead->next_action_at->isToday())
                <p class="flex items-center gap-1.5 text-xs font-medium text-red-600"><x-signal-dot color="red" /> Opvolging verlopen ({{ $lead->next_action_at->translatedFormat('j M') }})</p>
            @elseif ($lead->needsFollowUpToday())
                <p class="flex items-center gap-1.5 text-xs font-medium text-amber-600"><x-icon name="calendar" class="h-3.5 w-3.5" /> Vandaag opvolgen</p>
            @endif
            @if (config('renovion.modules.quotes'))
                @foreach ($lead->quotes->filter(fn ($quote) => $quote->status->isOpen() && $quote->daysOpen() !== null && $quote->daysOpen() >= 3) as $openQuote)
                    <p class="flex items-center gap-1.5 text-xs font-medium text-amber-600"><x-signal-dot color="amber" /> Offerte {{ $openQuote->daysOpen() }} dagen open</p>
                    @if ($openQuote->viewed_count > 0)
                        <p class="flex items-center gap-1.5 text-xs font-medium text-gray-500"><x-icon name="eye" class="h-3.5 w-3.5" /> {{ $openQuote->viewed_count }}× bekeken</p>
                    @endif
                @endforeach
            @endif
        </div>
    </a>

    @if ($lead->customer->phone)
        <a href="tel:{{ $lead->customer->phone }}"
           class="mt-3 flex items-center justify-center gap-1.5 rounded-lg bg-brand-600 py-1.5 text-center text-xs font-semibold text-white transition hover:bg-brand-500">
            <x-icon name="phone" class="h-3.5 w-3.5" /> Contact opnemen
        </a>
    @else
        <a href="{{ route('leads.edit', $lead) }}"
           class="mt-3 block rounded-lg bg-gray-100 py-1.5 text-center text-xs font-semibold text-gray-600 transition hover:bg-gray-200">
            Gegevens aanvullen
        </a>
    @endif
</div>
