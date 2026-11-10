<x-layouts.app title="Gesprek opnemen">

    <div x-data="meetingRecorder({ storeUrl: @js(route('meetings.store')), project: @js($selectedProject ? (string) $selectedProject : ''), kind: 'intern' })" class="mx-auto max-w-xl">

        {{-- Stap 1: waar gaat het over, en weet iedereen het --}}
        <template x-if="step === 'form'">
            <div>
                <x-page-header eyebrow="Nova notuleert" category="planning" title="Gesprek opnemen" subtitle="Nova luistert mee en maakt het verslag: samenvatting, besluiten, acties en open vragen." />

                <section class="space-y-4 rounded-2xl border border-gray-200 bg-white p-5">
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold text-gray-700">Onderwerp</span>
                        <input type="text" x-model="title" maxlength="255" placeholder="Bijv. Bouwvergadering badkamer" class="w-full rounded-xl border-gray-300 text-sm placeholder:text-gray-400 focus:border-brand-500 focus:ring-brand-500">
                    </label>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="block min-w-0">
                            <span class="mb-1 block text-sm font-semibold text-gray-700">Soort gesprek</span>
                            <select x-model="kind" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                                @foreach ($kinds as $kind)
                                    <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block min-w-0">
                            <span class="mb-1 block text-sm font-semibold text-gray-700">Project</span>
                            <select x-model="projectId" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="">Geen project</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 bg-gray-50 p-3">
                        <input type="checkbox" x-model="consent" class="mt-0.5 h-5 w-5 shrink-0 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                        <span class="text-sm text-gray-700">Alle aanwezigen weten dat het gesprek wordt opgenomen en zijn akkoord.</span>
                    </label>
                    <p class="-mt-2 text-xs text-gray-500">Vraagt iemand wat er met de opname gebeurt? <a href="{{ route('privacy') }}#gesprekken" class="font-semibold text-brand-700 underline underline-offset-2">Zo gaan we ermee om</a>.</p>

                    <p x-show="error" x-cloak class="rounded-xl bg-red-50 px-3 py-2 text-sm font-medium text-red-700" x-text="error"></p>

                    <button type="button" @click="start()" :disabled="busy"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-500 py-3.5 text-base font-bold text-white transition hover:bg-brand-600 active:bg-brand-600 disabled:opacity-60">
                        <span class="h-3 w-3 rounded-full bg-white"></span>
                        <span x-text="busy ? 'Microfoon openen' : 'Opname starten'"></span>
                    </button>

                    <ul class="space-y-1.5 text-xs text-gray-500">
                        <li class="flex gap-2"><x-icon name="sun" class="h-4 w-4 shrink-0 text-gray-400" /> Houd deze pagina open, het scherm blijft aan tijdens de opname.</li>
                        <li class="flex gap-2"><x-icon name="clock" class="h-4 w-4 shrink-0 text-gray-400" /> Na Stop maakt Nova het verslag, dat duurt een paar minuten. Jij kiest welke acties taken worden.</li>
                        <li class="flex gap-2"><x-icon name="eye" class="h-4 w-4 shrink-0 text-gray-400" /> Het geluid wordt {{ config('renovion.transcription.driver') === 'assemblyai' ? 'in de EU' : 'door OpenAI' }} omgezet naar tekst en na {{ config('renovion.transcription.audio_retention_days') }} dagen verwijderd. Het verslag blijft staan.</li>
                    </ul>
                </section>
            </div>
        </template>

        {{-- Stap 2: opname loopt --}}
        <template x-if="step !== 'form'">
            <section data-no-enter class="mt-4 rounded-2xl border border-gray-200 bg-white p-6 text-center">
                <p class="flex items-center justify-center gap-2 text-sm font-semibold" :class="paused ? 'text-gray-500' : 'text-red-700'">
                    <span class="h-2.5 w-2.5 rounded-full" :class="paused ? 'bg-gray-400' : 'animate-pulse bg-red-500'"></span>
                    <span x-text="step === 'stopping' ? 'Opname gestopt' : (paused ? 'Gepauzeerd' : 'Nova luistert mee')"></span>
                </p>
                <p class="mt-1 truncate text-base font-bold text-navy-950" x-text="title"></p>
                <p class="mt-4 font-[family-name:var(--font-display)] text-5xl font-bold text-navy-950 tabular-nums" x-text="time"></p>

                <div class="mt-5 flex h-8 items-center justify-center gap-1" aria-hidden="true">
                    <template x-for="(level, index) in levels" :key="index">
                        <span class="w-1.5 rounded-full bg-brand-500 transition-[height] duration-100" :style="`height: ${level}px`"></span>
                    </template>
                </div>

                <p class="mt-4 min-h-5 text-xs" :class="offline ? 'font-semibold text-amber-700' : 'text-gray-500'"
                   x-text="offline ? 'Geen verbinding: ' + pending + ' ' + (pending === 1 ? 'stukje wacht' : 'stukjes wachten') + ', die gaan vanzelf zodra het weer kan.' : (step === 'stopping' ? (pending ? 'Laatste stukjes versturen' : 'Verslag klaarzetten') : 'Alles is veilig verstuurd.')"></p>
                <p x-show="error" x-cloak class="mt-2 text-xs font-medium text-red-700" x-text="error"></p>

                <div class="mt-6 flex gap-2" x-show="step === 'recording'">
                    <button type="button" @click="pause()" class="flex-1 rounded-xl border border-gray-300 bg-white py-3 text-sm font-semibold text-navy-900 transition hover:bg-gray-50 active:bg-gray-100" x-text="paused ? 'Hervatten' : 'Pauzeren'"></button>
                    <button type="button" @click="stop()" class="flex-[2] rounded-xl bg-brand-500 py-3 text-sm font-bold text-white transition hover:bg-brand-600 active:bg-brand-600">Stoppen en verslag maken</button>
                </div>
                <x-skeleton x-show="step !== 'recording'" x-cloak :lines="2" class="mt-6" />
            </section>
        </template>
    </div>

</x-layouts.app>
