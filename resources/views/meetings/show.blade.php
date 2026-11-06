<x-layouts.app :title="$meeting->title">
    @php
        $canDelete = auth()->user()->can('manage-crm') || $meeting->user_id === auth()->id();
        $duration = $meeting->duration_seconds ? gmdate($meeting->duration_seconds >= 3600 ? 'G:i:s' : 'i:s', $meeting->duration_seconds) : null;
    @endphp

    <nav class="mb-3 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs font-semibold text-gray-600">
        <a href="{{ route('meetings.index') }}" class="tap hover:text-brand-600">Gespreksverslagen</a>
        @if ($meeting->project)
            <x-icon name="chevron-right" class="h-3 w-3" />
            <a href="{{ route('projects.show', $meeting->project) }}" class="tap hover:text-brand-600">{{ $meeting->project->name }}</a>
        @endif
    </nav>

    <x-page-header eyebrow="Gespreksverslag" category="planning" :title="$meeting->title"
                   :subtitle="collect([$meeting->kind->label(), $meeting->started_at->translatedFormat('j M Y H:i'), $duration, $meeting->user?->name])->filter()->implode(', ')">
        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $meeting->status->badgeClasses() }}">{{ $meeting->status->label() }}</span>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="min-w-0 space-y-4 lg:col-span-2">

            {{-- Nog bezig: elke paar seconden kijken (en daarmee de verwerking een stap verder helpen) --}}
            @if ($meeting->status === \App\Enums\MeetingStatus::Verwerken)
                <section class="rounded-2xl border border-gray-200 bg-white p-5"
                         x-data="{ timer: null, init() { this.timer = setInterval(async () => { const r = await fetch(@js(route('meetings.status', $meeting)), { headers: { Accept: 'application/json' } }).then((x) => x.json()).catch(() => null); if (r && r.status !== 'verwerken') location.reload(); }, 5000); }, destroy() { clearInterval(this.timer); } }">
                    <div class="mb-4 flex items-center gap-3">
                        <x-nova-avatar class="ring-2 ring-brand-500" />
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-navy-900">Nova schrijft het verslag</p>
                            <p class="text-xs text-gray-500">
                                @if ($waitingFor)
                                    {{ $waitingFor }} De opname is bewaard en wordt verwerkt zodra die er is.
                                @else
                                    Het gesprek wordt omgezet naar tekst, daarna haalt Nova er de besluiten, acties en vragen uit. Dit duurt een paar minuten; je kunt deze pagina gerust verlaten.
                                @endif
                            </p>
                        </div>
                    </div>
                    <x-skeleton :lines="4" />
                </section>
            @elseif ($meeting->status === \App\Enums\MeetingStatus::Opname)
                <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                    <p class="text-sm font-bold text-amber-900">Deze opname is niet afgerond</p>
                    <p class="mt-1 text-sm text-amber-800">De opname loopt nog, of de pagina werd gesloten voordat er op Stop werd gedrukt. Wat er al binnen is ({{ $duration ?? '0:00' }}), kan Nova toch verwerken.</p>
                    @if ($meeting->user_id === auth()->id())
                        <form method="POST" action="{{ route('meetings.stop', $meeting) }}" class="mt-3"
                              x-data @submit.prevent="postJson($el.action, {}).then(() => location.reload())">
                            @csrf
                            <button type="submit" class="rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600 active:bg-brand-600">Toch verwerken</button>
                        </form>
                    @endif
                </section>
            @elseif ($meeting->status === \App\Enums\MeetingStatus::Mislukt)
                <section class="rounded-2xl border border-red-200 bg-red-50 p-5">
                    <p class="text-sm font-bold text-red-800">Verslag maken lukte niet</p>
                    <p class="mt-1 text-sm text-red-700">{{ $meeting->error }}</p>
                    <form method="POST" action="{{ route('meetings.retry', $meeting) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600 active:bg-brand-600">Opnieuw proberen</button>
                    </form>
                </section>
            @endif

            @if ($meeting->status === \App\Enums\MeetingStatus::Klaar)
                {{-- Samenvatting --}}
                <section class="rounded-2xl border border-gray-200 bg-white p-5">
                    <div class="mb-2 flex items-center gap-2">
                        <x-nova-avatar class="h-7 w-7" />
                        <h2 class="text-sm font-bold text-navy-900">Samenvatting</h2>
                    </div>
                    <p class="text-sm whitespace-pre-line text-gray-700">{{ $meeting->summary ?: 'Geen samenvatting.' }}</p>
                    <p class="mt-3 text-xs text-gray-500">Concept van Nova op basis van het transcript. Controleer het voordat je het deelt.</p>
                </section>

                @if ($meeting->decisions)
                    <section class="rounded-2xl border border-gray-200 bg-white p-5">
                        <h2 class="mb-3 flex items-center gap-2 text-sm font-bold text-navy-900"><x-icon name="check" class="h-4 w-4 text-green-700" /> Besluiten</h2>
                        <ul class="space-y-2">
                            @foreach ($meeting->decisions as $decision)
                                <li class="rounded-xl bg-green-50 px-3 py-2 text-sm text-green-900">{{ $decision }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- Acties: aanvinken wordt een taak, eigenaar en datum zijn aan te passen --}}
                @if ($meeting->actions)
                    <section class="rounded-2xl border border-gray-200 bg-white p-5">
                        <h2 class="mb-3 flex items-center gap-2 text-sm font-bold text-navy-900"><x-icon name="clipboard-check" class="h-4 w-4 text-planning-700" /> Acties</h2>
                        <form method="POST" action="{{ route('meetings.tasks', $meeting) }}" class="space-y-2">
                            @csrf
                            @foreach ($meeting->actions as $index => $action)
                                @if ($action['task_id'])
                                    <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 p-3">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-green-500 text-white"><x-icon name="check" class="h-3.5 w-3.5" /></span>
                                        <span class="min-w-0 flex-1 text-sm text-gray-600">{{ $action['title'] }}</span>
                                        <a href="{{ route('tasks.index') }}" class="tap shrink-0 text-xs font-semibold text-brand-700">Taak</a>
                                    </div>
                                    <input type="hidden" name="actions[{{ $index }}][title]" value="{{ $action['title'] }}">
                                @else
                                    <div class="rounded-xl border border-gray-200 p-3" x-data="{ on: true }">
                                        <label class="flex cursor-pointer items-start gap-3">
                                            <input type="checkbox" name="actions[{{ $index }}][selected]" value="1" x-model="on" class="mt-1 h-5 w-5 shrink-0 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                                            {{-- Meerdere regels: een lange actie mag niet afgekapt worden --}}
                                            <textarea name="actions[{{ $index }}][title]" rows="2" maxlength="255" aria-label="Actie"
                                                      class="min-w-0 flex-1 resize-none rounded-lg border-transparent px-2 py-1 text-sm font-medium text-navy-900 [field-sizing:content] hover:border-gray-200 focus:border-brand-500 focus:ring-brand-500">{{ $action['title'] }}</textarea>
                                        </label>
                                        <div class="mt-2 grid grid-cols-1 gap-2 min-[420px]:grid-cols-2 min-[420px]:pl-8" x-show="on">
                                            <select name="actions[{{ $index }}][owner_id]" aria-label="Eigenaar" class="min-w-0 rounded-lg border-gray-200 py-1.5 text-xs focus:border-brand-500 focus:ring-brand-500">
                                                <option value="">Ik zelf</option>
                                                @foreach ($team as $member)
                                                    <option value="{{ $member->id }}" @selected($action['owner_id'] === $member->id)>{{ $member->name }}</option>
                                                @endforeach
                                            </select>
                                            <input type="date" name="actions[{{ $index }}][deadline]" value="{{ $action['due'] }}" aria-label="Datum" class="min-w-0 rounded-lg border-gray-200 py-1.5 text-xs focus:border-brand-500 focus:ring-brand-500">
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                            @if (collect($meeting->actions)->contains(fn ($action) => $action['task_id'] === null))
                                <button type="submit" class="w-full rounded-xl bg-brand-500 py-3 text-sm font-bold text-white transition hover:bg-brand-600 active:bg-brand-600">Aangevinkte acties als taak aanmaken</button>
                            @endif
                        </form>
                    </section>
                @endif

                @if ($meeting->questions)
                    <section class="rounded-2xl border border-gray-200 bg-white p-5">
                        <h2 class="mb-3 flex items-center gap-2 text-sm font-bold text-navy-900"><x-icon name="question-mark-circle" class="h-4 w-4 text-amber-700" /> Open vragen</h2>
                        <ul class="space-y-2">
                            @foreach ($meeting->questions as $question)
                                <li class="rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ $question }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            @endif

            {{-- Transcript --}}
            @if ($meeting->transcript)
                <details class="group rounded-2xl border border-gray-200 bg-white">
                    <summary class="flex cursor-pointer list-none items-center justify-between p-5 text-sm font-bold text-navy-900 [&::-webkit-details-marker]:hidden">
                        Volledig transcript <span class="flex items-center gap-1 text-xs font-semibold text-gray-500">{{ \App\Support\Tally::of(count($meeting->transcript), 'uitspraak', 'uitspraken') }} <x-icon name="chevron-down" class="h-4 w-4 transition group-open:rotate-180" /></span>
                    </summary>
                    <div class="space-y-3 border-t border-gray-100 p-5">
                        @foreach ($meeting->transcript as $line)
                            <div class="flex gap-3 text-sm">
                                <span class="w-12 shrink-0 pt-0.5 text-xs text-gray-500 tabular-nums">{{ gmdate($line['start'] >= 3600000 ? 'G:i:s' : 'i:s', intdiv($line['start'], 1000)) }}</span>
                                <span class="min-w-0 flex-1">
                                    @if ($line['speaker'] !== '')
                                        <b class="block text-xs font-bold {{ ['A' => 'text-brand-700', 'B' => 'text-project-700', 'C' => 'text-calc-700', 'D' => 'text-lead-700'][$line['speaker']] ?? 'text-navy-900' }}">{{ $meeting->speakerName($line['speaker']) }}</b>
                                    @endif
                                    <span class="text-gray-700">{{ $line['text'] }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif
        </div>

        <div class="min-w-0 space-y-4">
            @if ($meeting->hasAudio() && $meeting->status !== \App\Enums\MeetingStatus::Opname)
                <section class="rounded-2xl border border-gray-200 bg-white p-5">
                    <h2 class="mb-3 text-sm font-bold text-navy-900">Opname</h2>
                    {{-- Opnames uit de browser (webm) hebben geen lengte in het bestand: één keer naar het eind
                         springen laat de browser de lengte uitrekenen, zodat de schuifbalk werkt. --}}
                    <audio controls preload="metadata" src="{{ route('meetings.audio', $meeting) }}" class="w-full"
                           x-data @loadedmetadata="if ($el.duration === Infinity) { $el.currentTime = 1e101; $el.addEventListener('timeupdate', () => { $el.currentTime = 0; }, { once: true }); }"></audio>
                    <p class="mt-2 text-xs text-gray-500">Wordt {{ $meeting->ended_at ? 'op '.$meeting->ended_at->copy()->addDays(config('renovion.transcription.audio_retention_days'))->translatedFormat('j M Y') : 'na '.config('renovion.transcription.audio_retention_days').' dagen' }} verwijderd. Het verslag blijft.</p>
                </section>
            @elseif ($meeting->audio_deleted_at)
                <section class="rounded-2xl border border-gray-200 bg-white p-5 text-sm text-gray-500">De opname is na de bewaartermijn verwijderd. Het verslag blijft bewaard.</section>
            @endif

            @if ($meeting->status === \App\Enums\MeetingStatus::Klaar && $channels->isNotEmpty())
                <section class="rounded-2xl border border-gray-200 bg-white p-5">
                    <h2 class="mb-3 text-sm font-bold text-navy-900">Delen in de chat</h2>
                    <form method="POST" action="{{ route('meetings.share', $meeting) }}" class="space-y-2">
                        @csrf
                        <select name="channel_id" aria-label="Kanaal" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($channels as $channel)
                                <option value="{{ $channel->id }}" @selected($meeting->project_id && $channel->project_id === $meeting->project_id)>{{ $channel->displayName() }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="w-full rounded-xl border border-gray-300 bg-white py-2.5 text-sm font-semibold text-navy-900 transition hover:bg-gray-50">Nova deelt de samenvatting</button>
                    </form>
                </section>
            @endif

            @if ($canDelete)
                <form method="POST" action="{{ route('meetings.destroy', $meeting) }}" onsubmit="return confirm('Verslag en opname definitief verwijderen?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="w-full rounded-xl border border-red-200 bg-white py-2.5 text-sm font-semibold text-red-700 transition hover:bg-red-50">Verslag en opname verwijderen</button>
                </form>
            @endif
        </div>
    </div>

</x-layouts.app>
