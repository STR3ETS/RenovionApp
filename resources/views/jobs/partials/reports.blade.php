@if ($canExecute)
    <div x-data="{ open: false, type: 'probleem' }" class="mb-4">
        <button @click="open = true"
                class="flex w-full items-center justify-center gap-2 rounded-2xl bg-brand-500 py-3.5 text-sm font-bold text-white transition hover:bg-brand-600">
            <x-icon name="exclamation-triangle" class="h-5 w-5" /> Melding maken
        </button>

        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-navy-950/70 backdrop-blur-sm sm:items-center">
            <div @click.outside="open = false" class="max-h-[92vh] w-full overflow-y-auto rounded-t-2xl border border-surface-700 bg-surface-800 p-5 sm:max-w-lg sm:rounded-2xl">
                <h2 class="mb-4 text-base font-bold text-white">Melding maken</h2>

                <form method="POST" action="{{ route('job-reports.store', $job) }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <input type="hidden" name="type" :value="type">

                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach (JobReportType::cases() as $soort)
                            <button type="button" @click="type = '{{ $soort->value }}'"
                                    :class="type === '{{ $soort->value }}' ? 'border-brand-500 bg-brand-500/10' : 'border-surface-700 bg-surface-850'"
                                    class="flex flex-col items-center gap-1.5 rounded-xl border p-3 text-center transition">
                                <x-icon :name="$soort->icon()" class="h-5 w-5 text-slate-300" />
                                <span class="text-[11px] leading-tight font-semibold text-slate-200">{{ $soort->label() }}</span>
                            </button>
                        @endforeach
                    </div>

                    <x-field label="Wat is er aan de hand?" name="body">
                        <textarea name="body" id="body" rows="3" required
                                  placeholder="Beschrijf kort wat je ziet en wat je nodig hebt"
                                  class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">{{ old('body') }}</textarea>
                    </x-field>

                    <div class="flex flex-wrap items-center gap-2">
                        <label class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-surface-600 px-3 py-2.5 text-sm font-semibold text-slate-200">
                            <x-icon name="camera" class="h-4 w-4" /> Foto toevoegen
                            <input type="file" name="photos[]" accept="image/*" capture="environment" multiple hidden>
                        </label>

                        <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-surface-600 px-3 py-2.5 text-sm font-semibold text-slate-200">
                            <input type="checkbox" name="urgent" value="1"
                                   class="h-4 w-4 rounded border-surface-600 bg-surface-750 text-brand-500 focus:ring-brand-500">
                            Spoed, werk ligt stil
                        </label>
                    </div>

                    <div class="flex gap-2 pt-1">
                        <button type="button" @click="open = false"
                                class="flex-1 rounded-xl border border-surface-600 py-3 text-sm font-semibold text-slate-300">Annuleren</button>
                        <button type="submit"
                                class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-brand-500 py-3 text-sm font-bold text-white transition hover:bg-brand-600">
                            <x-icon name="paper-airplane" class="h-4 w-4" /> Versturen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@if ($job->reports->isEmpty())
    <x-empty-state icon="check" category="project" title="Geen meldingen"
                   subtitle="Loopt er iets anders dan verwacht? Maak een melding, dan pakt de projectleider het op." />
@else
    <div class="space-y-2">
        @foreach ($job->reports as $melding)
            <div class="rounded-xl border bg-surface-800 p-4 {{ $melding->isOpen() ? 'border-surface-700' : 'border-surface-700 opacity-70' }}">
                <div class="flex items-start gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $melding->type->badgeClasses() }}">
                        <x-icon :name="$melding->type->icon()" class="h-4.5 w-4.5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2 text-sm font-bold text-white">
                            {{ $melding->type->label() }}
                            @if ($melding->urgent)
                                <span class="rounded-full bg-red-500/15 px-2 py-0.5 text-[10px] font-bold text-red-300">Spoed</span>
                            @endif
                        </p>
                        <p class="mt-1 text-sm whitespace-pre-line text-slate-300">{{ $melding->body }}</p>

                        @if ($melding->photos->isNotEmpty())
                            <div class="mt-2 flex gap-2 overflow-x-auto">
                                @foreach ($melding->photos as $foto)
                                    <img src="{{ route('photos.show', $foto) }}" alt="" class="h-20 w-20 shrink-0 rounded-lg object-cover">
                                @endforeach
                            </div>
                        @endif

                        <p class="mt-2 text-xs text-slate-500">
                            {{ $melding->created_at->translatedFormat('j M H:i') }}, {{ $melding->reporter->name }},
                            @if ($melding->isOpen())
                                wacht op reactie van {{ $project?->projectLeader?->name ?? 'de projectleider' }}
                            @else
                                afgehandeld door {{ $melding->resolver?->name }}
                            @endif
                        </p>

                        @if ($melding->resolution)
                            <p class="mt-2 rounded-lg bg-surface-850 p-3 text-xs text-slate-300">{{ $melding->resolution }}</p>
                        @endif
                    </div>
                </div>

                @can('manage-crm')
                    @if ($melding->isOpen())
                        <form method="POST" action="{{ route('job-reports.resolve', $melding) }}" class="mt-3 flex gap-2 border-t border-surface-700 pt-3">
                            @csrf
                            <input type="text" name="resolution" placeholder="Wat is er afgesproken?"
                                   class="min-w-0 flex-1 rounded-xl border-surface-600 bg-surface-850 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
                            <button type="submit" class="shrink-0 rounded-xl bg-brand-500 px-4 py-2 text-sm font-bold text-white">Afhandelen</button>
                        </form>
                    @endif
                @endcan
            </div>
        @endforeach
    </div>
@endif
