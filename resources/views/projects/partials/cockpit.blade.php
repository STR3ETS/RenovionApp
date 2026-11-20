@php
    use App\Enums\HealthLight;

    // De toon van een licht in de klassen van het prototype.
    $toon = fn (HealthLight $light) => match ($light) {
        HealthLight::Rood => 'r',
        HealthLight::Oranje => 'o',
        HealthLight::Groen => 'g',
        default => 'n',
    };
    $woord = fn (HealthLight $light) => match ($light) {
        HealthLight::Rood => 'Actie nodig',
        HealthLight::Oranje => 'Aandacht',
        HealthLight::Groen => 'Op koers',
        default => 'Nog niet van toepassing',
    };
    [$allesToon, $allesTekst] = match ($health->overall) {
        HealthLight::Rood => ['bad', 'Actie nodig'],
        HealthLight::Oranje => ['warn', 'Aandacht nodig'],
        HealthLight::Groen => ['ok', 'Alles op koers'],
        default => ['mut', 'Nog in voorbereiding'],
    };

    $eur = fn (float $v) => '€ '.number_format($v, 0, ',', '.');
    $costs = $cockpit->costs;
    $burn = $cockpit->burn();
    $progress = (int) $health->progress;
@endphp

{{-- De projectcockpit uit het prototype: eerst de vier stoplichten, dan de
     mijlpalen, en daaronder wat het project tegenhoudt naast het budget. --}}
<section class="ck">
    <div class="ck-head">
        <div>
            <span class="lbl">Projectcockpit</span>
            <h3>In een oogopslag</h3>
        </div>
        <span class="pill {{ $allesToon }} ck-all"><span class="hl hl-{{ $toon($health->overall) }}"></span>{{ $allesTekst }}</span>
    </div>

    <div class="ck-tiles">
        @foreach ($health->signals as $signal)
            <div class="ck-t ck-{{ $toon($signal->light) }}">
                <span class="ck-i"><x-icon :name="$signal->icon" /></span>
                <div>
                    <span>{{ $signal->label }}</span>
                    <b>{{ $woord($signal->light) }}</b>
                    <small>{{ $signal->reason }}</small>
                </div>
            </div>
        @endforeach
    </div>

    @if (count($cockpit->milestones) > 1)
        <div class="card ck-c ck-wide">
            <div class="card-h">
                <h3>Mijlpalen</h3>
                <small class="text-muted">
                    {{ $project->start_date?->translatedFormat('j M Y') }}@if ($project->start_date && $project->end_date_expected) tot @endif{{ $project->end_date_expected?->translatedFormat('j M Y') }}
                </small>
            </div>
            <div class="card-b">
                <div class="ck-tl">
                    <div class="ck-line"><i style="width: {{ $cockpit->elapsed() }}%"></i></div>
                    @if ($cockpit->today !== null)
                        <span class="ck-now" style="left: {{ $cockpit->today }}%"><b>Vandaag</b></span>
                    @endif
                    @foreach ($cockpit->milestones as $i => $milestone)
                        <div class="ck-ms {{ $milestone['done'] ? 'done' : ($milestone['late'] ? 'late' : '') }} {{ $i % 2 ? 'dn2' : '' }} {{ $loop->first ? 'first' : ($loop->last ? 'last' : '') }}"
                             style="left: {{ $milestone['position'] }}%">
                            <span class="ck-pt">@if ($milestone['done'])<x-icon name="check" />@endif</span>
                            <div>
                                <b>{{ $milestone['label'] }}</b>
                                <small>{{ $milestone['date']->translatedFormat('j M') }}</small>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="ck-grid">
        {{-- Wat het project tegenhoudt, en daarna wat er als eerste moet gebeuren --}}
        <div class="card ck-c">
            <div class="card-h">
                <h3>Acties en blokkades</h3>
                @if ($cockpit->blockers !== [])
                    <span class="pill bad">{{ count($cockpit->blockers) }}</span>
                @endif
            </div>
            <div class="card-b">
                @if ($cockpit->blockers !== [])
                    <span class="lbl" style="color: var(--bad)">Blokkades</span>
                    @foreach (array_slice($cockpit->blockers, 0, 4) as $blocker)
                        <div class="li">
                            <span class="ic {{ $blocker['tone'] }}"><x-icon :name="$blocker['icon']" /></span>
                            <div class="tx">
                                <b>{{ $blocker['text'] }}</b>
                                <small>Eigenaar: {{ $blocker['owner'] }}</small>
                            </div>
                            @if ($blocker['url'])
                                <a href="{{ $blocker['url'] }}" class="btn xs">Open</a>
                            @endif
                        </div>
                    @endforeach
                @endif

                <span class="lbl block {{ $cockpit->blockers !== [] ? 'mt-2.5' : '' }}">Volgende acties</span>
                @forelse ($tasks->take(5) as $task)
                    <div class="li">
                        <span class="ic {{ $task->isOverdue() ? 'bad' : ($task->deadline?->isToday() ? 'warn' : 'blue') }}"><x-icon name="clipboard-check" /></span>
                        <div class="tx">
                            <b>{{ $task->title }}</b>
                            <small>
                                {{ $task->owner ? str($task->owner->name)->before(' ') : 'niet toegewezen' }},
                                @if ($task->deadline)
                                    {{ $task->isOverdue() ? 'te laat, was '.$task->deadline->translatedFormat('j M') : ($task->deadline->isToday() ? 'vandaag' : $task->deadline->translatedFormat('j M')) }}
                                @else
                                    geen datum
                                @endif
                            </small>
                        </div>
                    </div>
                @empty
                    <p class="my-1.5 text-muted">Geen open taken.</p>
                @endforelse

                <a href="{{ route('tasks.index', ['project' => $project->id]) }}" class="btn sm mt-2"><x-icon name="plus" />Taak</a>
            </div>
        </div>

        {{-- Loopt het verbruikte budget ver voor op de voortgang, dan gaat het mis --}}
        <div class="card ck-c">
            <div class="card-h">
                <h3>Budget tegenover voortgang</h3>
                <a href="{{ route('projects.show', [$project, 'tab' => 'nacalculatie']) }}" class="btn xs ghost">Nacalculatie</a>
            </div>
            <div class="card-b">
                @if ($costs !== null)
                    <div class="ck-bv">
                        <div>
                            <div class="flex items-center justify-between text-[12.5px]"><span>Voortgang</span><b>{{ $progress }}%</b></div>
                            <div class="bar"><i style="width: {{ $progress }}%"></i></div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between text-[12.5px]"><span>Budget verbruikt</span><b class="{{ $burn > $progress + 10 ? 'dn' : '' }}">{{ $burn }}%</b></div>
                            <div class="bar ck-burn {{ $burn > $progress + 10 ? 'over' : ($burn > $progress ? 'warn' : '') }}"><i style="width: {{ min(100, $burn) }}%"></i></div>
                        </div>
                        <div class="sumrow">
                            <span class="text-muted">Begroot, werkelijk, prognose</span>
                            <span>{{ $eur($costs['budgeted']) }}, {{ $eur($costs['actual']) }}, <b>{{ $eur($costs['expected']) }}</b></span>
                        </div>
                        <div class="sumrow">
                            <span class="text-muted">Marge begroot en verwacht</span>
                            <span>{{ number_format($costs['margin_budgeted'], 0, ',', '.') }}%, <b class="{{ $costs['margin_expected'] < $costs['margin_budgeted'] - 2 ? 'dn' : 'up' }}">{{ number_format($costs['margin_expected'], 0, ',', '.') }}%</b></span>
                        </div>
                    </div>
                @else
                    <p class="text-muted">Nog geen kosten om te vergelijken. Dat kan zodra er een definitieve begroting is.</p>
                @endif
            </div>
        </div>
    </div>
</section>
