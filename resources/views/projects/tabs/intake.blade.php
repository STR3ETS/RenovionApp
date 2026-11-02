@php
    use App\Models\Intake;
@endphp

@if ($intake === null)
    <x-empty-state icon="clipboard-document-list" category="lead" title="Nog geen intake"
                   subtitle="De intake is het verschil tussen een calculatie die klopt en een die later misgaat.">
        @if ($project->lead)
            <form method="POST" action="{{ route('intakes.store') }}">
                @csrf
                <input type="hidden" name="lead_id" value="{{ $project->lead_id }}">
                <button type="submit" class="rounded-full bg-brand-500 px-4 py-2.5 text-sm font-bold text-white">Intake starten</button>
            </form>
        @endif
    </x-empty-state>
@else
    @php($score = $intake->completeness())

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-sm font-bold text-white">Antwoorden <span class="text-brand-500">uit de intake</span></h2>
                    <a href="{{ route('intakes.show', $intake) }}" class="rounded-full border border-surface-600 px-3.5 py-2 text-xs font-semibold text-slate-300 transition hover:text-white">
                        Intake openen
                    </a>
                </div>

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

                @if ($intake->summary)
                    <div class="mt-4 border-t border-surface-700 pt-4">
                        <p class="mb-1 text-[11px] font-bold tracking-widest text-slate-500 uppercase">Samenvatting</p>
                        <p class="text-sm whitespace-pre-line text-slate-200">{{ $intake->summary }}</p>
                    </div>
                @endif
            </section>
        </div>

        <div class="space-y-4">
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-1 text-sm font-bold text-white">Volledig<span class="text-brand-500">heid</span></h2>
                <p class="mb-3 text-xs text-slate-500">Wat een calculator nodig heeft om te kunnen rekenen.</p>

                <div class="mb-3 flex items-center gap-3">
                    <span class="h-2 flex-1 overflow-hidden rounded-full bg-surface-700">
                        <span class="block h-full rounded-full {{ $score['percentage'] === 100 ? 'bg-green-500' : 'bg-amber-500' }}"
                              style="width: {{ $score['percentage'] }}%"></span>
                    </span>
                    <span class="text-sm font-bold text-white">{{ $score['percentage'] }}%</span>
                </div>

                @if ($score['missing'] === [])
                    <p class="text-sm text-green-400">Alles wat nodig is, staat erin.</p>
                @else
                    <ul class="space-y-1">
                        @foreach ($score['missing'] as $ontbreekt)
                            <li class="flex items-start gap-2 text-sm text-amber-300">
                                <x-icon name="x-mark" class="mt-0.5 h-3.5 w-3.5 shrink-0" /> {{ $ontbreekt }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @if ($intake->survey_on)
                <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    <h2 class="mb-2 text-sm font-bold text-white">Opname</h2>
                    <p class="text-sm text-slate-200">
                        {{ $intake->survey_on->translatedFormat('l j F') }}@if ($intake->survey_at), {{ $intake->survey_at }} @endif
                    </p>
                    <p class="text-xs text-slate-500">met {{ $intake->surveyor?->name }}</p>
                </section>
            @endif

            <section class="rounded-2xl border border-brand-500/25 bg-brand-500/5 p-5">
                <div class="mb-2 flex items-center gap-2.5">
                    <x-nova-avatar class="h-8 w-8 shrink-0 ring-2 ring-brand-500" />
                    <h2 class="text-sm font-bold text-white">Nova <span class="text-brand-500">vraagt</span></h2>
                </div>
                <ul class="space-y-1.5">
                    @foreach ($intake->suggestedQuestions() as $vraag)
                        <li class="flex items-start gap-2 text-sm text-slate-200">
                            <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-brand-500"></span> {{ $vraag }}
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    </div>
@endif
