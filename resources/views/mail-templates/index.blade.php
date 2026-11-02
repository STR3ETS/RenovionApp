@php
    use App\Models\MailTemplate;
@endphp

<x-layouts.app title="Mailteksten">
    <x-page-header title="Mailteksten" eyebrow="Organisatie" category="klant"
                   subtitle="De teksten die naar klanten gaan, op één plek in plaats van in de hoofden van vier mensen." />

    <div class="grid gap-4 xl:grid-cols-3">
        {{-- De templates --}}
        <div class="space-y-3 xl:col-span-2">
            @foreach ($templates as $template)
                @php($unknown = $template->unknownVariables())
                <div x-data="{ open: {{ $preview?->id === $template->id ? 'true' : 'false' }} }"
                     class="overflow-hidden rounded-2xl border bg-surface-800 {{ $unknown !== [] ? 'border-amber-500/30' : 'border-surface-700' }}">
                    <button @click="open = !open" class="flex w-full flex-wrap items-center gap-3 p-4 text-left transition hover:bg-white/5">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-klant-500/15 text-klant-400">
                            <x-icon name="envelope" class="h-5 w-5" />
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-2">
                                <b class="truncate text-sm font-bold text-white">{{ $template->name }}</b>
                                @unless ($template->active)
                                    <span class="rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-bold text-slate-400">Uit</span>
                                @endunless
                                @if ($unknown !== [])
                                    <span class="rounded-full bg-amber-500/15 px-2 py-0.5 text-[10px] font-bold text-amber-300">
                                        Onbekende variabele
                                    </span>
                                @endif
                            </span>
                            <small class="mt-0.5 block truncate text-xs text-slate-400">{{ $template->subject }}</small>
                            @if ($template->moment)
                                <small class="block truncate text-xs text-slate-500">{{ $template->moment }}</small>
                            @endif
                        </span>

                        <a href="{{ route('mail-templates.index', ['voorbeeld' => $template->id, 'project' => $project?->id]) }}"
                           class="shrink-0 rounded-xl border border-surface-600 px-3 py-2 text-xs font-semibold text-slate-300 transition hover:text-white">
                            Voorbeeld
                        </a>
                    </button>

                    <form x-show="open" x-cloak method="POST" action="{{ route('mail-templates.update', $template) }}"
                          class="space-y-3 border-t border-surface-700 bg-surface-850 p-4">
                        @csrf
                        @method('PATCH')

                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-field label="Naam" name="name">
                                <input type="text" name="name" value="{{ $template->name }}"
                                       class="w-full rounded-xl border-surface-600 bg-surface-800 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                            </x-field>
                            <x-field label="Wanneer stuur je deze" name="moment">
                                <input type="text" name="moment" value="{{ $template->moment }}"
                                       class="w-full rounded-xl border-surface-600 bg-surface-800 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                            </x-field>
                        </div>

                        <x-field label="Onderwerp" name="subject">
                            <input type="text" name="subject" value="{{ $template->subject }}"
                                   class="w-full rounded-xl border-surface-600 bg-surface-800 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                        </x-field>

                        <div class="space-y-2">
                            <p class="text-sm font-medium text-slate-200">Blokken</p>
                            @foreach ($template->blocks ?? [] as $index => $block)
                                <div class="flex gap-2">
                                    <select name="blocks[{{ $index }}][type]"
                                            class="w-32 shrink-0 rounded-xl border-surface-600 bg-surface-800 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                                        @foreach ($blockTypes as $key => $meta)
                                            <option value="{{ $key }}" @selected(($block['type'] ?? '') === $key)>{{ $meta['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <textarea name="blocks[{{ $index }}][body]" rows="{{ max(2, substr_count($block['body'] ?? '', "\n") + 1) }}"
                                              class="min-w-0 flex-1 rounded-xl border-surface-600 bg-surface-800 text-sm text-white focus:border-brand-500 focus:ring-brand-500">{{ $block['body'] ?? '' }}</textarea>
                                </div>
                            @endforeach

                            {{-- Een leeg blok om er een bij te zetten --}}
                            @php($next = count($template->blocks ?? []))
                            <div class="flex gap-2">
                                <select name="blocks[{{ $next }}][type]"
                                        class="w-32 shrink-0 rounded-xl border-surface-600 bg-surface-800 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                                    @foreach ($blockTypes as $key => $meta)
                                        <option value="{{ $key }}">{{ $meta['label'] }}</option>
                                    @endforeach
                                </select>
                                <textarea name="blocks[{{ $next }}][body]" rows="2" placeholder="Nieuw blok, laat leeg om niets toe te voegen"
                                          class="min-w-0 flex-1 rounded-xl border-surface-600 bg-surface-800 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500"></textarea>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <label class="flex items-center gap-2 text-sm text-slate-300">
                                <input type="checkbox" name="active" value="1" @checked($template->active)
                                       class="rounded border-surface-600 bg-surface-750 text-brand-500 focus:ring-brand-500">
                                In gebruik
                            </label>
                            <button type="submit" class="ml-auto rounded-xl bg-brand-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-600">
                                Opslaan
                            </button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>

        {{-- Voorbeeld en variabelen --}}
        <div class="space-y-4">
            @if ($preview)
                <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    <h2 class="mb-1 text-sm font-bold text-white">Voor<span class="text-brand-500">beeld</span></h2>
                    <p class="mb-3 text-xs text-slate-500">
                        Gevuld met een echt project. Wat niet gevuld kan worden blijft als ontbrekend staan.
                    </p>

                    <form method="GET" class="mb-3">
                        <input type="hidden" name="voorbeeld" value="{{ $preview->id }}">
                        <select name="project" onchange="this.form.submit()"
                                class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($projects as $p)
                                <option value="{{ $p->id }}" @selected($project?->id === $p->id)>
                                    {{ $p->name }}{{ $p->customer ? ', '.$p->customer->name : '' }}
                                </option>
                            @endforeach
                        </select>
                    </form>

                    <div class="rounded-xl bg-white p-4 text-sm text-slate-900">
                        <p class="mb-3 border-b border-slate-200 pb-2 font-bold">{{ $preview->renderSubject($values) }}</p>
                        <p class="leading-relaxed whitespace-pre-line">{{ $preview->render($values) }}</p>
                    </div>
                </div>
            @endif

            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-1 text-sm font-bold text-white">Vari<span class="text-brand-500">abelen</span></h2>
                <p class="mb-3 text-xs text-slate-500">Zet ze tussen dubbele accolades in de tekst.</p>

                <div class="space-y-1">
                    @foreach ($variables as $key => $label)
                        {{-- De accolades los opbouwen: Blade leest geneste accolades in
                             een echo anders als een eigen uitdrukking. --}}
                        @php($token = str_repeat('{', 2).$key.str_repeat('}', 2))
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <code class="rounded bg-surface-700 px-1.5 py-0.5 font-mono text-slate-300">{{ $token }}</code>
                            <span class="min-w-0 truncate text-right text-slate-500">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-2xl border border-amber-500/25 bg-amber-500/5 p-5">
                <p class="text-sm text-slate-200">
                    <b class="text-white">Er gaat nog niets echt de deur uit.</b>
                    Zonder mailserver legt het systeem vast wat het zou versturen, maar verstuurt het niets.
                    Dat staat bij Instellingen onder Omgeving.
                </p>
            </div>
        </div>
    </div>
</x-layouts.app>
