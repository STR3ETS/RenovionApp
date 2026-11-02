@php
    use App\Models\Intake;
    $klant = $intake->customer ?? $intake->lead?->customer;
@endphp

<x-layouts.app title="Intake">
    <div class="mb-4">
        <a href="{{ route('intakes.index') }}" class="flex items-center gap-1 text-sm font-semibold text-slate-400 transition hover:text-white">
            <x-icon name="chevron-left" class="h-4 w-4" /> Intakes
        </a>
    </div>

    <x-page-header :title="$klant?->name ?? 'Intake'" eyebrow="Intake" category="lead"
                   :subtitle="collect([$klant?->address, $klant?->city, $intake->lead?->service])->filter()->join(', ')">
        <span class="rounded-full px-3 py-1.5 text-xs font-bold {{ $completeness['percentage'] === 100 ? 'bg-green-500/15 text-green-300' : 'bg-amber-500/15 text-amber-300' }}">
            {{ $completeness['percentage'] }}% compleet
        </span>

        @unless ($intake->isComplete())
            <form method="POST" action="{{ route('intakes.complete', $intake) }}">
                @csrf
                <button type="submit" class="flex items-center gap-1.5 rounded-full bg-brand-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
                    <x-icon name="check" class="h-4 w-4" /> Intake afronden
                </button>
            </form>
        @endunless
    </x-page-header>

    {{-- De vijf stappen --}}
    <nav class="scrollbar-none mb-5 flex gap-2 overflow-x-auto">
        @foreach (Intake::STEPS as $index => [$label, $hint])
            <a href="{{ route('intakes.show', [$intake, 'stap' => $index]) }}"
               class="flex shrink-0 items-center gap-2.5 rounded-xl border px-4 py-3 transition {{ $index === $step ? 'border-brand-500 bg-brand-500/10' : 'border-surface-700 bg-surface-800 hover:border-surface-600' }}">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $index < $intake->step ? 'bg-green-500/20 text-green-300' : ($index === $step ? 'bg-brand-500 text-white' : 'bg-surface-700 text-slate-400') }}">
                    @if ($index < $intake->step)
                        <x-icon name="check" class="h-3.5 w-3.5" />
                    @else
                        {{ $index + 1 }}
                    @endif
                </span>
                <span class="text-left">
                    <span class="block text-sm font-bold {{ $index === $step ? 'text-white' : 'text-slate-300' }}">{{ $label }}</span>
                    <span class="block text-[11px] text-slate-500">{{ $hint }}</span>
                </span>
            </a>
        @endforeach
    </nav>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @if ($step === 0)
                {{-- Stap 1: klant en adres, komt uit het klantdossier --}}
                <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    <h2 class="mb-4 text-sm font-bold text-white">Klant <span class="text-brand-500">en adres</span></h2>

                    @if ($klant)
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-slate-400">Naam</dt><dd class="text-slate-200">{{ $klant->name }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-400">Adres</dt><dd class="text-right text-slate-200">{{ collect([$klant->address, $klant->postcode, $klant->city])->filter()->join(', ') ?: 'niet bekend' }}</dd></div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-400">Telefoon</dt>
                                <dd class="{{ blank($klant->phone) ? 'font-semibold text-red-400' : 'text-slate-200' }}">{{ $klant->phone ?: 'ontbreekt' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-400">E-mailadres</dt><dd class="text-slate-200">{{ $klant->email ?: 'ontbreekt' }}</dd></div>
                        </dl>

                        <a href="{{ route('customers.show', $klant) }}" class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-brand-400 transition hover:text-brand-300">
                            Naar het klantdossier <x-icon name="arrow-right" class="h-3.5 w-3.5" />
                        </a>
                    @else
                        <p class="text-sm text-slate-500">Nog geen klantdossier gekoppeld.</p>
                    @endif

                    <form method="POST" action="{{ route('intakes.update', $intake) }}" class="mt-4 border-t border-surface-700 pt-4">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="step" value="0">
                        <button type="submit" class="rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
                            Naar het belgesprek
                        </button>
                    </form>
                </div>
            @elseif ($step === 2)
                {{-- Stap 3: de opname inplannen --}}
                <form method="POST" action="{{ route('intakes.survey', $intake) }}" class="space-y-4 rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    @csrf
                    <h2 class="text-sm font-bold text-white">Opname <span class="text-brand-500">inplannen</span></h2>
                    <p class="text-xs text-slate-400">Dit zet de afspraak meteen in de planning van wie gaat.</p>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-field label="Wie gaat" name="surveyor_id">
                            <select name="surveyor_id" id="surveyor_id" required
                                    class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                                @foreach ($surveyors as $person)
                                    <option value="{{ $person->id }}" @selected($intake->surveyor_id === $person->id)>
                                        {{ $person->name }}, {{ $person->job_title ?: $person->role->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </x-field>
                        <x-field label="Datum" name="survey_on">
                            <input type="date" name="survey_on" id="survey_on" required value="{{ $intake->survey_on?->toDateString() }}"
                                   class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                        </x-field>
                        <x-field label="Tijd" name="survey_at">
                            <input type="time" name="survey_at" id="survey_at" value="{{ $intake->survey_at }}"
                                   class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                        </x-field>
                    </div>

                    <button type="submit" class="rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
                        Opname plannen
                    </button>
                </form>
            @elseif ($step === 4)
                {{-- Stap 5: samenvatting --}}
                <form method="POST" action="{{ route('intakes.update', $intake) }}" class="space-y-4 rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="step" value="4">

                    <h2 class="text-sm font-bold text-white">Samen<span class="text-brand-500">vatting</span></h2>

                    <x-field label="Hoe kansrijk is dit?" name="answers[kwalificatie]">
                        <input type="text" name="answers[kwalificatie]" id="answers[kwalificatie]"
                               value="{{ $intake->answer('kwalificatie') }}"
                               placeholder="Koud, warm of heet, en waarom"
                               class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
                    </x-field>

                    <x-field label="Samenvatting voor de calculator" name="summary">
                        <textarea name="summary" id="summary" rows="8"
                                  placeholder="Wat moet de calculator weten dat niet in de losse velden past?"
                                  class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">{{ $intake->summary }}</textarea>
                    </x-field>

                    <button type="submit" class="rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">Opslaan</button>
                </form>

                {{-- Wat er in de intake staat, op één rij --}}
                <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    <h2 class="mb-4 text-sm font-bold text-white">Alle <span class="text-brand-500">antwoorden</span></h2>
                    <dl class="space-y-3">
                        @foreach (Intake::FIELDS as $key => $field)
                            <div>
                                <dt class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">{{ $field['label'] }}</dt>
                                <dd class="mt-0.5 text-sm {{ $intake->answer($key) ? 'whitespace-pre-line text-slate-200' : 'text-slate-600 italic' }}">
                                    {{ $intake->answer($key) ?? 'niet ingevuld' }}
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @else
                {{-- Stap 2 en 4: de velden van die stap --}}
                <form method="POST" action="{{ route('intakes.update', $intake) }}" class="space-y-4 rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="step" value="{{ $step }}">

                    <h2 class="text-sm font-bold text-white">{{ Intake::STEPS[$step][0] }}</h2>

                    @foreach ($fields as $key => $field)
                        <x-field :label="$field['label']" :name="'answers['.$key.']'">
                            <textarea name="answers[{{ $key }}]" id="answers[{{ $key }}]" rows="2"
                                      placeholder="{{ $field['hint'] }}"
                                      class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">{{ $intake->answer($key) }}</textarea>
                        </x-field>
                    @endforeach

                    <button type="submit" class="rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
                        Opslaan en verder
                    </button>
                </form>
            @endif
        </div>

        <div class="space-y-4">
            {{-- Nova's vragen --}}
            <div class="rounded-2xl border border-brand-500/25 bg-brand-500/5 p-5">
                <div class="mb-3 flex items-center gap-2.5">
                    <x-nova-avatar class="h-8 w-8 shrink-0 ring-2 ring-brand-500" />
                    <h2 class="text-sm font-bold text-white">Nova <span class="text-brand-500">vraagt</span></h2>
                </div>

                <ul class="space-y-2">
                    @foreach ($questions as $vraag)
                        <li class="flex items-start gap-2 text-sm text-slate-200">
                            <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-brand-500"></span>
                            {{ $vraag }}
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Volledigheid --}}
            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-1 text-sm font-bold text-white">Volledig<span class="text-brand-500">heid</span></h2>
                <p class="mb-3 text-xs text-slate-500">Deze antwoorden heeft een calculator nodig om te kunnen rekenen.</p>

                <div class="mb-3 flex items-center gap-3">
                    <span class="h-2 flex-1 overflow-hidden rounded-full bg-surface-700">
                        <span class="block h-full rounded-full {{ $completeness['percentage'] === 100 ? 'bg-green-500' : 'bg-amber-500' }}"
                              style="width: {{ $completeness['percentage'] }}%"></span>
                    </span>
                    <span class="text-sm font-bold text-white">{{ $completeness['percentage'] }}%</span>
                </div>

                @if ($completeness['missing'] === [])
                    <p class="text-sm text-green-400">Alles wat nodig is, staat erin.</p>
                @else
                    <p class="mb-2 text-xs font-bold tracking-widest text-slate-500 uppercase">Nog nodig</p>
                    <ul class="space-y-1">
                        @foreach ($completeness['missing'] as $ontbreekt)
                            <li class="flex items-start gap-2 text-sm text-amber-300">
                                <x-icon name="x-mark" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                {{ $ontbreekt }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @if ($intake->survey_on)
                <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    <h2 class="mb-2 text-sm font-bold text-white">Opname</h2>
                    <p class="text-sm text-slate-200">
                        {{ $intake->survey_on->translatedFormat('l j F') }}@if ($intake->survey_at), {{ $intake->survey_at }} @endif
                    </p>
                    <p class="mt-0.5 text-xs text-slate-500">met {{ $intake->surveyor?->name }}</p>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
