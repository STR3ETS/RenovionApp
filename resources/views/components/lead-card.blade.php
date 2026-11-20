@props(['lead'])

{{-- Leadcard volgens briefing: alleen wat nodig is om een beslissing te nemen. --}}
<div {{ $attributes->merge(['class' => 'rounded-xl border border-surface-700 border-t-2 border-t-lead-500 bg-surface-800 p-3 shadow-sm transition hover:shadow-md']) }}>
    <a href="{{ route('leads.show', $lead) }}" class="block">
        <div class="flex items-start justify-between gap-2">
            <p class="font-semibold text-ink">{{ $lead->customer->name }}</p>
            @if ($lead->qualification !== \App\Enums\LeadQualification::Onbeoordeeld)
                <x-signal-dot :color="$lead->qualification->dotColor()" class="mt-1.5" title="Kwalificatie: {{ $lead->qualification->label() }}" />
            @endif
        </div>
        <p class="text-xs text-muted">{{ $lead->customer->city ?? 'Plaats onbekend' }}</p>
        @if ($lead->service)
            <p class="mt-1 text-sm text-ink">{{ $lead->service }}</p>
        @endif
        @if ($lead->value)
            <p class="mt-1 text-sm font-bold text-ink">€ {{ number_format((float) $lead->value, 0, ',', '.') }}</p>
        @endif

        <div class="mt-2 space-y-1">
            @if ($lead->missingPhone())
                <p class="flex items-center gap-1.5 text-xs font-medium text-amber-400"><x-signal-dot color="amber" /> Geen telefoonnummer</p>
            @endif
            @if ($lead->next_action_at?->isPast() && ! $lead->next_action_at->isToday())
                <p class="flex items-center gap-1.5 text-xs font-medium text-red-400"><x-signal-dot color="red" /> Opvolging verlopen ({{ $lead->next_action_at->translatedFormat('j M') }})</p>
            @elseif ($lead->needsFollowUpToday())
                <p class="flex items-center gap-1.5 text-xs font-medium text-amber-400"><x-icon name="calendar" class="h-3.5 w-3.5" /> Vandaag opvolgen</p>
            @elseif ($lead->isSilent())
                <p class="flex items-center gap-1.5 text-xs font-medium text-amber-400"><x-signal-dot color="amber" /> Geen opvolging gepland</p>
            @endif
            @if ($lead->status->isOpen() && $lead->qualification === \App\Enums\LeadQualification::Onbeoordeeld)
                <p class="flex items-center gap-1.5 text-xs font-medium text-faint"><x-signal-dot color="gray" /> Nog kwalificeren</p>
            @endif
            @if (config('renovion.modules.quotes'))
                @foreach ($lead->quotes->filter(fn ($quote) => $quote->status->isOpen() && $quote->daysOpen() !== null && $quote->daysOpen() >= 3) as $openQuote)
                    <p class="flex items-center gap-1.5 text-xs font-medium text-amber-400"><x-signal-dot color="amber" /> Offerte {{ $openQuote->daysOpen() }} dagen open</p>
                    @if ($openQuote->viewed_count > 0)
                        <p class="flex items-center gap-1.5 text-xs font-medium text-muted"><x-icon name="eye" class="h-3.5 w-3.5" /> {{ $openQuote->viewed_count }}× bekeken</p>
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
           class="mt-3 block rounded-lg bg-surface-750 py-1.5 text-center text-xs font-semibold text-ink2 transition hover:bg-surface-700">
            Gegevens aanvullen
        </a>
    @endif
</div>
