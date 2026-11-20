@php
    use App\Enums\CallOutcome;
    use App\Enums\NextActionType;

    $tabs = array_filter([
        'afspraak' => $canCrm ? 'Afspraak' : null,
        'taak' => 'Taak',
        'terugbel' => $canCrm ? 'Terugbelverzoek' : null,
        'belpoging' => $canCrm ? 'Belpoging' : null,
    ]);
    $veld = 'mt-1 w-full rounded-xl border-surface-600 text-sm';
@endphp

{{-- Nieuw in de agenda: afspraak, taak, terugbelverzoek of belpoging --}}
<div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-0 backdrop-blur-sm sm:items-center sm:p-4"
     @click.self="open = false" role="dialog" aria-modal="true" aria-label="Nieuw in de agenda">
    <div class="motion-pop max-h-[92vh] w-full overflow-y-auto rounded-t-2xl border border-surface-700 bg-surface-800 p-5 shadow-2xl shadow-black/50 sm:max-w-lg sm:rounded-2xl">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="text-base font-bold text-ink">Nieuw in de <span class="text-brand-500">agenda</span></h2>
            <button type="button" @click="open = false" class="flex h-9 w-9 items-center justify-center rounded-full border border-surface-600 text-ink2 hover:text-ink" aria-label="Sluiten">
                <x-icon name="x-mark" class="h-4 w-4" />
            </button>
        </div>

        <nav class="scrollbar-none mb-4 flex gap-1 overflow-x-auto rounded-full border border-surface-700 bg-surface-850 p-1">
            @foreach ($tabs as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'bg-brand-500 text-white' : 'text-muted hover:text-ink'"
                        class="shrink-0 rounded-full px-3.5 py-2 text-sm font-semibold whitespace-nowrap transition">{{ $label }}</button>
            @endforeach
        </nav>

        @if ($canCrm)
            {{-- Afspraak --}}
            <form x-show="tab === 'afspraak'" method="POST" action="{{ route('planning.store') }}" class="space-y-3">
                @csrf
                <label class="block text-xs text-muted">Waar gaat het over
                    <input type="text" name="title" required maxlength="255" class="{{ $veld }}" placeholder="Bijvoorbeeld opname badkamer">
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="block text-xs text-muted">Soort
                        <select name="type" class="{{ $veld }}">
                            <option value="afspraak">Afspraak</option>
                            <option value="intake">Intake</option>
                            <option value="overig">Overleg</option>
                        </select>
                    </label>
                    <label class="block text-xs text-muted">Van wie
                        <select name="user_id" x-model="user" class="{{ $veld }}">
                            @foreach ($team as $member)
                                <option value="{{ $member->id }}">{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <label class="block text-xs text-muted">Datum
                        <input type="date" name="date" x-model="date" required class="{{ $veld }}">
                    </label>
                    <label class="block text-xs text-muted">Van
                        <input type="time" name="start_time" x-model="time" class="{{ $veld }}">
                    </label>
                    <label class="block text-xs text-muted">Tot
                        <input type="time" name="end_time" class="{{ $veld }}">
                    </label>
                </div>
                <fieldset>
                    <legend class="text-xs text-muted">Wie is er verder bij</legend>
                    <div class="mt-1 flex max-h-28 flex-wrap gap-x-4 gap-y-1.5 overflow-y-auto rounded-xl border border-surface-700 p-3">
                        @foreach ($team as $member)
                            <label class="flex items-center gap-1.5 text-sm text-ink2">
                                <input type="checkbox" name="participants[]" value="{{ $member->id }}" class="rounded"> {{ $member->name }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <button type="submit" class="btn pri lg w-full">Afspraak inplannen</button>
            </form>
        @endif

        {{-- Taak met een tijd --}}
        <form x-show="tab === 'taak'" method="POST" action="{{ route('tasks.store') }}" class="space-y-3">
            @csrf
            <label class="block text-xs text-muted">Wat moet er gebeuren
                <input type="text" name="title" required maxlength="255" class="{{ $veld }}">
            </label>
            <div class="grid grid-cols-2 gap-3">
                <label class="block text-xs text-muted">Datum
                    <input type="date" name="deadline" x-model="date" required class="{{ $veld }}">
                </label>
                <label class="block text-xs text-muted">Tijd
                    <input type="time" name="due_time" x-model="time" class="{{ $veld }}">
                </label>
            </div>
            @if ($canCrm)
                <label class="block text-xs text-muted">Voor wie
                    <select name="owner_id" x-model="user" class="{{ $veld }}">
                        @foreach ($team as $member)
                            <option value="{{ $member->id }}">{{ $member->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <p class="text-xs text-faint">Met een tijd staat de taak op het raster, zonder tijd bovenaan de dag.</p>
            <button type="submit" class="btn pri lg w-full">Taak aanmaken</button>
        </form>

        @if ($canCrm)
            {{-- Terugbelverzoek: de klant wil op een afgesproken moment gebeld worden --}}
            <form x-show="tab === 'terugbel'" method="POST" action="{{ route('call-attempts.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="outcome" value="{{ CallOutcome::Terugbelverzoek->value }}">
                <label class="block text-xs text-muted">Welke aanvraag
                    <select name="lead_id" x-model="lead" required class="{{ $veld }}">
                        <option value="">Kies een aanvraag</option>
                        @foreach ($leads as $lead)
                            <option value="{{ $lead->id }}">{{ $lead->customer->name }}{{ $lead->service ? ', '.$lead->service : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="block text-xs text-muted">Terugbellen op
                        <input type="date" name="next_date" x-model="date" required class="{{ $veld }}">
                    </label>
                    <label class="block text-xs text-muted">Om
                        <input type="time" name="next_time" x-model="time" class="{{ $veld }}">
                    </label>
                </div>
                <label class="block text-xs text-muted">Notitie
                    <input type="text" name="note" maxlength="2000" class="{{ $veld }}" placeholder="Bijvoorbeeld: na vijven bereikbaar">
                </label>
                <button type="submit" class="btn pri lg w-full">Terugbelverzoek vastleggen</button>
            </form>

            {{-- Belpoging: wat gebeurde er toen je belde --}}
            <form x-show="tab === 'belpoging'" method="POST" action="{{ route('call-attempts.store') }}" class="space-y-3"
                  x-data="{ uitkomst: '{{ CallOutcome::Gesproken->value }}' }">
                @csrf
                <label class="block text-xs text-muted">Welke aanvraag
                    <select name="lead_id" x-model="lead" required class="{{ $veld }}">
                        <option value="">Kies een aanvraag</option>
                        @foreach ($leads as $lead)
                            <option value="{{ $lead->id }}">
                                {{ $lead->customer->name }}{{ $lead->customer->phone ? ', '.$lead->customer->phone : '' }}{{ $lead->missed_calls > 0 ? ', '.$lead->missed_calls.' keer geen gehoor' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <fieldset>
                    <legend class="text-xs text-muted">Uitkomst</legend>
                    <div class="mt-1 grid grid-cols-2 gap-2">
                        @foreach ([CallOutcome::Gesproken, CallOutcome::GeenGehoor, CallOutcome::Voicemail] as $outcome)
                            <label class="flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-semibold transition"
                                   :class="uitkomst === '{{ $outcome->value }}' ? 'border-brand-500 bg-brand-500/10 text-ink' : 'border-surface-600 text-ink2'">
                                <input type="radio" name="outcome" value="{{ $outcome->value }}" x-model="uitkomst" class="sr-only">
                                {{ $outcome->label() }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div x-show="uitkomst !== '{{ CallOutcome::Gesproken->value }}'" class="space-y-2">
                    <p class="text-xs text-muted">Opnieuw proberen</p>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 rounded-xl border border-surface-600 px-3 py-2.5 text-sm text-ink2">
                            <input type="radio" name="retry" value="plus2" checked> Over twee uur
                        </label>
                        <label class="flex items-center gap-2 rounded-xl border border-surface-600 px-3 py-2.5 text-sm text-ink2">
                            <input type="radio" name="retry" value="morgen"> Morgen om tien uur
                        </label>
                    </div>
                    <label x-show="uitkomst === '{{ CallOutcome::Voicemail->value }}'" class="flex items-center gap-2 text-sm text-ink2">
                        <input type="checkbox" name="mail_sent" value="1" class="rounded"> Ik heb er ook een mail achteraan gestuurd
                    </label>
                </div>

                <div x-show="uitkomst === '{{ CallOutcome::Gesproken->value }}'" class="grid grid-cols-3 gap-3">
                    <label class="block text-xs text-muted">Vervolg
                        <select name="next_type" class="{{ $veld }}">
                            @foreach (NextActionType::cases() as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-xs text-muted">Op
                        <input type="date" name="next_date" class="{{ $veld }}">
                    </label>
                    <label class="block text-xs text-muted">Om
                        <input type="time" name="next_time" class="{{ $veld }}">
                    </label>
                </div>

                <label class="block text-xs text-muted">Notitie
                    <input type="text" name="note" maxlength="2000" class="{{ $veld }}" placeholder="Wat is er besproken">
                </label>

                <button type="submit" class="btn pri lg w-full">Belpoging vastleggen</button>
            </form>
        @endif
    </div>
</div>
