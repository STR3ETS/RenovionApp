@php
    use App\Enums\CallOutcome;
@endphp

{{-- Belpoging: in twee klikken vastleggen wat er gebeurde toen je belde --}}
<section class="rounded-2xl border border-surface-700 bg-surface-800 p-5" x-data="{ uitkomst: '' }">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-bold text-ink">Bel<span class="text-brand-500">poging</span></h2>
        @if ($lead->missed_calls > 0)
            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $lead->isUnreached() ? 'bg-red-500/15 text-red-300' : 'bg-amber-500/15 text-amber-300' }}">
                {{ $lead->missed_calls }} keer geen gehoor
            </span>
        @endif
    </div>

    @if ($advice = $lead->callAdvice())
        <div class="mb-3 flex items-start gap-2 rounded-xl border border-amber-500/25 bg-amber-500/10 p-3">
            <x-nova-avatar class="h-6 w-6 shrink-0" />
            <p class="text-sm text-ink">{{ $advice }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('call-attempts.store') }}" class="space-y-3">
        @csrf
        <input type="hidden" name="lead_id" value="{{ $lead->id }}">

        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            @foreach (CallOutcome::cases() as $outcome)
                <label class="flex min-h-[44px] cursor-pointer items-center justify-center rounded-xl border px-3 py-2.5 text-center text-sm font-semibold transition"
                       :class="uitkomst === '{{ $outcome->value }}' ? 'border-brand-500 bg-brand-500/10 text-ink' : 'border-surface-700 text-ink2 hover:border-surface-600'">
                    <input type="radio" name="outcome" value="{{ $outcome->value }}" x-model="uitkomst" required class="sr-only">
                    {{ $outcome->label() }}
                </label>
            @endforeach
        </div>

        <div x-show="uitkomst === '{{ CallOutcome::GeenGehoor->value }}' || uitkomst === '{{ CallOutcome::Voicemail->value }}'" x-cloak class="space-y-2">
            <div class="grid grid-cols-2 gap-2">
                <label class="flex items-center gap-2 rounded-xl border border-surface-700 px-3 py-2.5 text-sm text-ink2">
                    <input type="radio" name="retry" value="plus2" checked> Opnieuw over twee uur
                </label>
                <label class="flex items-center gap-2 rounded-xl border border-surface-700 px-3 py-2.5 text-sm text-ink2">
                    <input type="radio" name="retry" value="morgen"> Morgen om tien uur
                </label>
            </div>
            <label x-show="uitkomst === '{{ CallOutcome::Voicemail->value }}'" class="flex items-center gap-2 text-sm text-ink2">
                <input type="checkbox" name="mail_sent" value="1" class="rounded"> Ik heb er ook een mail achteraan gestuurd
            </label>
        </div>

        <div x-show="uitkomst === '{{ CallOutcome::Terugbelverzoek->value }}' || uitkomst === '{{ CallOutcome::Gesproken->value }}'" x-cloak class="grid grid-cols-2 gap-3">
            <label class="block text-xs text-muted">
                <span x-text="uitkomst === '{{ CallOutcome::Terugbelverzoek->value }}' ? 'Terugbellen op' : 'Vervolg op, als dat er is'"></span>
                <input type="date" name="next_date" class="mt-1 w-full rounded-xl border-surface-600 text-sm"
                       :required="uitkomst === '{{ CallOutcome::Terugbelverzoek->value }}'">
            </label>
            <label class="block text-xs text-muted">Om
                <input type="time" name="next_time" class="mt-1 w-full rounded-xl border-surface-600 text-sm">
            </label>
        </div>
        @error('next_date')<p class="text-xs font-medium text-red-400">{{ $message }}</p>@enderror

        <div x-show="uitkomst !== ''" x-cloak class="flex gap-2">
            <input type="text" name="note" maxlength="2000" placeholder="Notitie, als die er is"
                   class="min-w-0 flex-1 rounded-full border-surface-600 text-sm">
            <button type="submit" class="btn pri shrink-0">Vastleggen</button>
        </div>
    </form>
</section>
