<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Offerte bouwen — {{ $quote->number }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter-tight:400,500,600,700,800" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] { display: none !important; }
        body { overflow: hidden; }
        .no-scrollbar::-webkit-scrollbar { width: 0; height: 0; }
        .no-scrollbar { scrollbar-width: none; }
        .canvas-scroll::-webkit-scrollbar { width: 8px; background: transparent; }
        .canvas-scroll::-webkit-scrollbar-thumb { background: rgba(7, 7, 36, 0.12); border-radius: 999px; }
        .canvas-scroll::-webkit-scrollbar-thumb:hover { background: rgba(7, 7, 36, 0.25); }
        .block-wrapper { position: relative; outline: 2px solid transparent; outline-offset: 5px; border-radius: 18px; transition: outline-color .15s; }
        .block-wrapper:hover { outline-color: rgba(248, 91, 11, .3); }
        .block-wrapper.selected { outline-color: rgba(248, 91, 11, .6); }
        .block-wrapper.kept { outline-color: rgba(248, 91, 11, .45); }
        .block-wrapper.dragging { opacity: .4; }
        .block-wrapper.drag-over { outline-color: rgba(248, 91, 11, .9) !important; outline-style: dashed !important; }
        .block-actions { position: absolute; top: 50%; right: -18px; transform: translateY(-50%); z-index: 20; display: flex; flex-direction: column; gap: 4px; opacity: 0; transition: opacity .15s; }
        .block-wrapper:hover .block-actions, .block-wrapper.selected .block-actions { opacity: 1; }
        .block-action-btn { width: 30px; height: 30px; border-radius: 999px; background: #fff; border: 1px solid rgb(229 231 235); display: flex; align-items: center; justify-content: center; color: #374151; cursor: pointer; box-shadow: 0 2px 8px rgba(0, 0, 0, .06); transition: all .15s; }
        .block-action-btn:hover { background: #070724; border-color: #070724; color: #fff; }
        .block-action-btn.danger:hover { background: #ef4444; border-color: #ef4444; }
        .block-action-btn.drag-handle { cursor: grab; }
        .block-action-btn.drag-handle:active { cursor: grabbing; }
        .block-action-btn.pinned { background: #F85B0B; border-color: #F85B0B; color: #fff; opacity: 1; }
        @keyframes blockFlash {
            0%   { outline-color: rgba(248, 91, 11, 0); box-shadow: 0 0 0 rgba(248, 91, 11, 0); }
            30%  { outline-color: rgba(248, 91, 11, .9); box-shadow: 0 0 24px rgba(248, 91, 11, .25); }
            100% { outline-color: rgba(248, 91, 11, 0); box-shadow: 0 0 0 rgba(248, 91, 11, 0); }
        }
        .block-flash { animation: blockFlash 1.2s ease-out; }
    </style>
</head>
<body class="flex h-screen flex-col bg-gray-100 font-sans text-gray-900 antialiased"
      x-data="quoteBuilder({{ Js::from([
          'saveUrl' => route('quotes.builder.update', $quote),
          'applyTplUrl' => route('quotes.builder.template', $quote),
          'saveTplUrl' => route('quotes.builder.save-template', $quote),
          'deleteTplBase' => url('/offerte-templates'),
          'uploadUrl' => route('quotes.builder.upload', $quote),
          'aiUrl' => route('quotes.builder.ai', $quote),
          'generateUrl' => route('quotes.builder.generate', $quote),
          'showUrl' => route('quotes.show', $quote),
          'blocks' => $quote->blocks ?? [],
          'registry' => $registry,
          'templates' => $templates->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'description' => $t->description, 'is_default' => (bool) $t->is_default])->all(),
      ]) }})"
      @keydown.ctrl.s.prevent.window="save()"
      @keydown.meta.s.prevent.window="save()">

    {{-- Topbar --}}
    <header class="flex h-14 shrink-0 items-center justify-between gap-4 border-b border-gray-200 bg-white px-4 whitespace-nowrap lg:px-6">
        <div class="flex min-w-0 items-center gap-3">
            <a href="{{ route('quotes.show', $quote) }}" class="flex items-center gap-1 text-xs font-semibold text-gray-500 transition hover:text-navy-900">
                <x-icon name="chevron-right" class="h-3.5 w-3.5 rotate-180" /> Terug
            </a>
            <div class="h-5 w-px bg-gray-200"></div>
            <div class="min-w-0">
                <p class="truncate text-sm font-bold text-navy-950">Offerte bouwen <span class="font-semibold text-gray-400">· {{ $quote->number }} · v{{ $quote->version }}</span></p>
                <p class="truncate text-xs text-gray-400">{{ $quote->customer->name }}{{ $quote->lead?->service ? ' · '.$quote->lead->service : '' }}</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <span class="hidden items-center gap-1.5 text-xs font-semibold xl:flex"
                  :class="saveError ? 'text-red-600' : (dirty ? 'text-amber-600' : 'text-gray-400')"
                  title="Wijzigingen worden ook vanzelf opgeslagen. Sneltoets: Ctrl+S">
                <span x-show="dirty && !saveError" x-cloak class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                <span x-text="saveStatus()"></span>
            </span>
            <button type="button" @click="save()" :disabled="saving"
                    :class="saveError ? 'bg-red-50 text-red-600 hover:bg-red-100' : (dirty || saving ? 'bg-navy-950 text-white hover:bg-navy-900' : 'bg-gray-100 text-gray-500 hover:bg-gray-200')"
                    class="h-9 rounded-full px-4 text-xs font-bold transition disabled:cursor-wait"
                    title="Opslaan (Ctrl+S)">
                <span x-text="saving ? 'Opslaan…' : (saveError ? 'Opnieuw proberen' : (dirty ? 'Opslaan' : 'Opgeslagen'))"></span>
            </button>
            <div class="h-5 w-px bg-gray-200"></div>
            <a href="{{ route('quotes.show', $quote) }}" class="h-9 rounded-full bg-brand-500 px-5 text-xs leading-9 font-bold text-white transition hover:bg-brand-600" title="Terug naar de offertepagina — daar verstuur je de offerte ook">Klaar</a>
        </div>
    </header>

    <div class="flex min-h-0 flex-1">

        {{-- Links: blokbibliotheek --}}
        <aside class="hidden w-60 shrink-0 flex-col border-r border-gray-200 bg-white lg:flex">
            <div class="border-b border-gray-200 p-4">
                <p class="text-[10px] font-bold tracking-widest text-gray-400 uppercase">Blokken</p>
                <p class="mt-1 text-xs text-gray-400">Klik om toe te voegen</p>
            </div>
            <div class="no-scrollbar flex-1 space-y-1 overflow-y-auto p-3">
                @foreach ($registry as $item)
                    @continue($item['locked'] ?? false)
                    <button type="button" @click="addBlock('{{ $item['type'] }}')" :disabled="isDisabled('{{ $item['type'] }}')"
                            class="group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition hover:bg-brand-50 disabled:cursor-not-allowed disabled:opacity-40">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-500 transition group-hover:bg-brand-100">
                            <x-icon :name="$item['icon']" class="h-4 w-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-xs font-bold text-navy-900">{{ $item['label'] }}</span>
                            <span class="block truncate text-[11px] text-gray-400">{{ $item['description'] }}</span>
                        </span>
                        @if ($item['singleton'] ?? false)
                            <span x-show="hasBlock('{{ $item['type'] }}')" x-cloak title="Al toegevoegd"><x-icon name="check" class="h-3.5 w-3.5 shrink-0 text-brand-500" /></span>
                        @endif
                    </button>
                @endforeach
            </div>
        </aside>

        {{-- Midden: canvas, het live document --}}
        <main class="canvas-scroll flex-1 overflow-y-auto">
            <div class="mx-auto max-w-[800px] space-y-4 px-6 py-6 lg:px-8">

                {{-- Lege staat --}}
                <template x-if="blocks.length === 0">
                    <div class="rounded-2xl border border-gray-200 bg-white p-12 text-center">
                        <span class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-brand-50 text-brand-500"><x-icon name="document-text" class="h-6 w-6" /></span>
                        <p class="text-sm font-bold text-navy-900">Nog geen blokken</p>
                        <p class="mt-1 text-xs text-gray-400">Begin met een template (rechts) of voeg losse blokken toe (links).</p>
                    </div>
                </template>

                <template x-for="(block, index) in blocks" :key="block.id">
                    <div class="block-wrapper"
                         :class="{ selected: selectedIdx === index, 'drag-over': dragOver === index && dragFrom !== index, dragging: dragFrom === index, kept: block.data._keep }"
                         :data-block-id="block.id"
                         :draggable="dragHandle === index"
                         @click="selectedIdx = index"
                         @dragstart="dragFrom = index"
                         @dragover.prevent="dragOver = index"
                         @drop.prevent="dropBlock(index)"
                         @dragend="dragFrom = null; dragOver = null; dragHandle = null">

                        {{-- Vastgezet-badge --}}
                        <div x-show="block.data._keep" x-cloak class="absolute top-3 left-3 z-10 rounded-full bg-brand-500 px-2 py-0.5 text-[10px] font-bold text-white shadow-sm">Vast bij Nova</div>

                        {{-- Zwevende blokacties --}}
                        <div class="block-actions">
                            <button type="button" class="block-action-btn drag-handle" @mousedown="dragHandle = index" @mouseup="dragHandle = null" @click.stop title="Sleep om te verplaatsen">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                            </button>
                            <button type="button" class="block-action-btn" :class="{ pinned: block.data._keep }" @click.stop="toggleKeep(index)"
                                    :title="block.data._keep ? 'Vastgezet: blijft staan als Nova de offerte opnieuw bouwt' : 'Vastzetten: blijft staan als Nova de offerte opnieuw bouwt'">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                            </button>
                            <button type="button" class="block-action-btn" x-show="block.type !== 'divider'" @click.stop="openAi(index)" title="Laat Nova dit blok schrijven">
                                <img src="{{ asset('nova.jpg') }}" alt="Nova" class="h-5 w-5 rounded-full object-cover">
                            </button>
                            <button type="button" class="block-action-btn" x-show="!isSingleton(block.type)" @click.stop="duplicateBlock(index)" title="Dupliceren">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 8.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v8.25A2.25 2.25 0 0 0 6 16.5h2.25m8.25-8.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-8.25A2.25 2.25 0 0 1 7.5 18v-7.5a2.25 2.25 0 0 1 2.25-2.25h6.75Z" /></svg>
                            </button>
                            <button type="button" class="block-action-btn danger" x-show="!isLocked(block.type)" @click.stop="removeBlock(index)" title="Verwijderen">
                                <x-icon name="trash" class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        {{-- Render per bloktype --}}
                        @foreach ($registry as $item)
                            <template x-if="block.type === '{{ $item['type'] }}'">
                                <div>
                                    @include('quotes.blocks.'.$item['type'], ['mode' => 'edit', 'quote' => $quote])
                                </div>
                            </template>
                        @endforeach
                    </div>
                </template>

                <p x-show="blocks.length > 0" class="pb-2 text-center text-xs text-gray-400">Tekst pas je direct in het document aan. Beweeg over een blok voor slepen, Nova, dupliceren of verwijderen.</p>
            </div>
        </main>

        {{-- Rechts: templates + Nova --}}
        <aside class="hidden w-[300px] shrink-0 flex-col border-l border-gray-200 bg-white xl:flex">
            <div class="flex border-b border-gray-200">
                <button type="button" @click="rightTab = 'templates'"
                        :class="rightTab === 'templates' ? 'border-brand-500 text-navy-950' : 'border-transparent text-gray-400 hover:text-gray-600'"
                        class="h-12 flex-1 border-b-2 text-[11px] font-bold tracking-wider uppercase transition">Templates</button>
                <button type="button" @click="rightTab = 'nova'"
                        :class="rightTab === 'nova' ? 'border-brand-500 text-navy-950' : 'border-transparent text-gray-400 hover:text-gray-600'"
                        class="h-12 flex-1 border-b-2 text-[11px] font-bold tracking-wider uppercase transition">Nova</button>
            </div>

            {{-- Tab: templates --}}
            <div x-show="rightTab === 'templates'" class="no-scrollbar flex-1 space-y-3 overflow-y-auto p-4">
                <p class="text-xs text-gray-400">Klik op een template om hem toe te passen. Dat vervangt alle blokken van deze offerte; klantgegevens worden ingevuld.</p>
                <button type="button" @click="openTplModal()" :disabled="blocks.length === 0"
                        class="w-full rounded-full bg-brand-50 py-2.5 text-xs font-bold text-brand-600 transition hover:bg-brand-100 disabled:cursor-not-allowed disabled:opacity-40">
                    Deze offerte bewaren als template
                </button>

                <template x-for="tpl in templates" :key="tpl.id">
                    <div class="group relative">
                        <button type="button" @click="applyTemplate(tpl)"
                                class="w-full rounded-2xl border border-gray-200 p-4 text-left transition hover:border-brand-400 hover:bg-brand-50/50">
                            <p class="pr-5 text-xs font-bold text-navy-900" x-text="tpl.name"></p>
                            <p class="mt-1 text-[11px] leading-snug text-gray-400" x-text="tpl.description"></p>
                        </button>
                        <button type="button" x-show="!tpl.is_default" @click.stop="deleteTemplate(tpl)"
                                class="absolute top-2 right-2 flex h-6 w-6 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-300 opacity-0 transition group-hover:opacity-100 hover:text-red-500" title="Template verwijderen">
                            <x-icon name="trash" class="h-3 w-3" />
                        </button>
                    </div>
                </template>
            </div>

            {{-- Tab: Nova --}}
            <div x-show="rightTab === 'nova'" x-cloak class="no-scrollbar flex-1 space-y-4 overflow-y-auto p-4">
                <div class="rounded-2xl bg-gradient-to-br from-navy-950 to-navy-900 p-4 text-white">
                    <div class="mb-3 flex items-center gap-3">
                        <img src="{{ asset('nova.jpg') }}" alt="Nova" class="h-10 w-10 rounded-full object-cover ring-2 ring-brand-500">
                        <div>
                            <p class="text-sm font-bold">Nova bouwt de offerte</p>
                            <p class="text-[11px] text-navy-300">Jij controleert het resultaat</p>
                        </div>
                    </div>
                    <p class="mb-3 text-[11px] leading-relaxed text-navy-200">
                        Nova stelt een complete offerte samen op basis van de aanvraag, de klant en de calculatieposten. Beschrijf wat erin moet, of laat het leeg.
                    </p>
                    <div class="relative mb-3">
                        <textarea x-model="gen.prompt" rows="4"
                                  placeholder="Bijv. 'Complete badkamerrenovatie, benadruk de korte doorlooptijd en de garantie. Start in januari.'"
                                  class="w-full resize-none rounded-xl border-0 bg-navy-900 p-3 pr-10 text-xs leading-relaxed text-white placeholder:text-navy-400 focus:ring-2 focus:ring-brand-500"></textarea>
                        <button type="button" @click="toggleMic('quote')"
                                :class="(listening && speechTarget === 'quote') ? 'animate-pulse bg-red-500 text-white' : 'bg-white/10 text-navy-200 hover:bg-white/20'"
                                class="absolute right-2 bottom-2 flex h-7 w-7 items-center justify-center rounded-full transition"
                                :title="(listening && speechTarget === 'quote') ? 'Stop dicteren' : 'Inspreken'">
                            <x-icon name="microphone" class="h-3.5 w-3.5" />
                        </button>
                    </div>
                    <p x-show="listening && speechTarget === 'quote'" x-cloak class="mb-3 flex items-center gap-1.5 text-[11px] font-semibold text-red-400">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-red-400"></span> Aan het luisteren… klik op de microfoon om te stoppen.
                    </p>
                    <button type="button" @click="generate()" :disabled="gen.loading"
                            class="w-full rounded-full bg-brand-500 py-2.5 text-xs font-bold text-white transition hover:bg-brand-600 disabled:opacity-50">
                        <span x-text="gen.loading ? 'Nova bouwt de offerte…' : 'Bouw de offerte'"></span>
                    </button>
                    <p x-show="gen.error" x-text="gen.error" x-cloak class="mt-2 text-[11px] font-medium text-red-400"></p>
                    <p x-show="gen.loading" x-cloak class="mt-2 text-center text-[11px] text-navy-300">Dit kan ±15 seconden duren.</p>
                </div>

                <div class="rounded-xl border border-brand-100 bg-brand-50 p-3 text-[11px] leading-relaxed text-gray-600">
                    <span class="font-bold text-navy-900">Blok vastzetten?</span>
                    Zet een blok vast met het slotje (beweeg over een blok). Vastgezette blokken blijven mét inhoud staan; Nova bouwt alleen de rest opnieuw.
                </div>
                <div class="rounded-xl border border-amber-100 bg-amber-50 p-3 text-[11px] leading-relaxed text-amber-800">
                    Zonder vastgezette blokken vervangt Nova alle blokken (behalve de kop). Eén los blok bijwerken kan met de Nova-knop op dat blok.
                </div>
            </div>
        </aside>
    </div>

    {{-- Modal: bewaren als template --}}
    <div x-show="tplModal.open" x-cloak @keydown.escape.window="tplModal.open = false"
         class="fixed inset-0 z-50 flex items-center justify-center bg-navy-950/60 p-4 backdrop-blur-sm" @click.self="tplModal.open = false">
        <div class="w-full max-w-md rounded-2xl bg-white p-6">
            <h3 class="text-base font-bold text-navy-950">Bewaren als template</h3>
            <p class="mt-1 mb-4 text-xs text-gray-400">De blokken van deze offerte worden bewaard, zodat je ze bij een volgende offerte met één klik gebruikt. Klantgegevens vervang je in de tekst door <code class="rounded bg-gray-100 px-1">{{ '{klant}' }}</code> en <code class="rounded bg-gray-100 px-1">{{ '{plaats}' }}</code>.</p>

            <div class="mb-4 grid grid-cols-2 gap-1 rounded-full bg-gray-100 p-1">
                <button type="button" @click="tplModal.mode = 'nieuw'"
                        :class="tplModal.mode === 'nieuw' ? 'bg-white text-navy-950 shadow-sm' : 'text-gray-500'"
                        class="h-8 rounded-full text-xs font-bold transition">Nieuwe template</button>
                <button type="button" @click="tplModal.mode = 'bestaand'" :disabled="templates.length === 0"
                        :class="tplModal.mode === 'bestaand' ? 'bg-white text-navy-950 shadow-sm' : 'text-gray-500'"
                        class="h-8 rounded-full text-xs font-bold transition disabled:opacity-40">Bestaande bijwerken</button>
            </div>

            <div x-show="tplModal.mode === 'nieuw'" class="space-y-3">
                <input type="text" x-model="tplModal.name" maxlength="120" placeholder="Naam (bijv. Badkamer luxe)"
                       class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                <input type="text" x-model="tplModal.description" maxlength="255" placeholder="Korte omschrijving (optioneel)"
                       class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div x-show="tplModal.mode === 'bestaand'" x-cloak class="space-y-2">
                <select x-model.number="tplModal.template_id" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">Kies een template…</option>
                    <template x-for="tpl in templates" :key="tpl.id">
                        <option :value="tpl.id" x-text="tpl.name"></option>
                    </template>
                </select>
                <p class="rounded-xl border border-amber-100 bg-amber-50 px-3 py-2 text-[11px] font-medium text-amber-800">De blokken van die template worden vervangen door de blokken van deze offerte. Naam en omschrijving blijven staan.</p>
            </div>

            <p x-show="tplModal.error" x-text="tplModal.error" x-cloak class="mt-2 text-xs font-medium text-red-600"></p>
            <div class="mt-5 flex gap-2">
                <button type="button" @click="tplModal.open = false" class="flex-1 rounded-xl border border-gray-300 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Annuleren</button>
                <button type="button" @click="saveAsTemplate()"
                        :disabled="tplModal.saving || (tplModal.mode === 'nieuw' ? !tplModal.name.trim() : !tplModal.template_id)"
                        class="flex-1 rounded-xl bg-navy-950 py-2.5 text-sm font-bold text-white transition hover:bg-navy-900 disabled:opacity-50">
                    <span x-text="tplModal.saving ? 'Opslaan…' : (tplModal.mode === 'nieuw' ? 'Bewaren' : 'Template bijwerken')"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- Modal: Nova per blok --}}
    <div x-show="ai.open" x-cloak @keydown.escape.window="ai.open = false"
         class="fixed inset-0 z-50 flex items-center justify-center bg-navy-950/60 p-4 backdrop-blur-sm" @click.self="ai.open = false">
        <div class="w-full max-w-md rounded-2xl bg-white p-5">
            <div class="mb-3 flex items-center gap-3">
                <img src="{{ asset('nova.jpg') }}" alt="Nova" class="h-9 w-9 rounded-full object-cover ring-2 ring-brand-500">
                <div>
                    <p class="text-sm font-bold text-navy-900">Nova schrijft dit blok</p>
                    <p class="text-xs text-gray-400" x-text="'Blok: ' + aiLabel()"></p>
                </div>
            </div>
            <div class="relative">
                <textarea x-model="ai.prompt" rows="4" placeholder="Wat wil je? Bijv. 'maak dit persoonlijker en noem de start in november' — of laat leeg voor een voorstel op basis van de klantgegevens."
                          class="w-full resize-none rounded-xl border-gray-300 pr-10 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                <button type="button" @click="toggleMic('block')"
                        :class="(listening && speechTarget === 'block') ? 'animate-pulse bg-red-500 text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                        class="absolute right-2 bottom-3 flex h-7 w-7 items-center justify-center rounded-full transition"
                        :title="(listening && speechTarget === 'block') ? 'Stop dicteren' : 'Inspreken'">
                    <x-icon name="microphone" class="h-3.5 w-3.5" />
                </button>
            </div>
            <p x-show="listening && speechTarget === 'block'" x-cloak class="mt-1 flex items-center gap-1.5 text-[11px] font-semibold text-red-500">
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-red-500"></span> Aan het luisteren…
            </p>
            <p x-show="ai.error" x-text="ai.error" x-cloak class="mt-2 text-xs font-medium text-red-600"></p>
            <div class="mt-3 flex gap-2">
                <button type="button" @click="runAi()" :disabled="ai.loading"
                        class="flex-1 rounded-xl bg-brand-500 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600 disabled:opacity-50">
                    <span x-text="ai.loading ? 'Nova schrijft…' : 'Genereer'"></span>
                </button>
                <button type="button" @click="ai.open = false" class="rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Sluiten</button>
            </div>
        </div>
    </div>

    @include('quotes.partials.rich-editor')

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('quoteBuilder', (cfg) => ({
                cfg,
                blocks: cfg.blocks,
                registry: cfg.registry,
                templates: cfg.templates,
                rightTab: 'templates',
                selectedIdx: null,

                // Opslaan: vanzelf (1,5s na een wijziging), met Ctrl+S en een statusknop.
                dirty: false,
                saving: false,
                saveError: '',
                saveQueued: false,
                savePromise: null,
                savedAt: null,
                lastSaved: null,
                autosaveTimer: null,

                // Drag-and-drop
                dragFrom: null, dragOver: null, dragHandle: null,

                uploading: false,
                ai: { open: false, index: null, prompt: '', loading: false, error: null },
                gen: { prompt: '', loading: false, error: null },
                tplModal: { open: false, mode: 'nieuw', template_id: '', name: '', description: '', saving: false, error: null },

                // Dicteren (Web Speech API, nl-NL — net als Nova elders in de app)
                listening: false,
                speechTarget: null,
                recognition: null,

                init() {
                    this.lastSaved = this.snapshot();
                    this.$watch('blocks', () => this.checkDirty());
                    window.addEventListener('beforeunload', (event) => {
                        if (this.dirty || this.saving) { event.preventDefault(); event.returnValue = ''; }
                    });
                },

                meta(type) { return this.registry.find(item => item.type === type) || {}; },
                isLocked(type) { return this.meta(type).locked === true; },
                isSingleton(type) { return this.meta(type).singleton === true; },
                hasBlock(type) { return this.blocks.some(block => block.type === type); },
                isDisabled(type) { return this.isSingleton(type) && this.hasBlock(type); },
                newId() { return 'b_' + Math.random().toString(36).slice(2, 14); },

                snapshot() { return JSON.stringify(this.blocks); },
                checkDirty() {
                    this.dirty = this.snapshot() !== this.lastSaved;
                    clearTimeout(this.autosaveTimer);
                    if (this.dirty) this.autosaveTimer = setTimeout(() => this.save(), 1500);
                },
                saveStatus() {
                    if (this.saveError) return 'Niet opgeslagen: ' + this.saveError;
                    if (this.saving) return 'Bezig met opslaan';
                    if (this.dirty) return 'Niet opgeslagen wijzigingen';
                    if (this.savedAt) {
                        const pad = (n) => String(n).padStart(2, '0');
                        return 'Opgeslagen om ' + pad(this.savedAt.getHours()) + ':' + pad(this.savedAt.getMinutes());
                    }
                    return 'Alles is opgeslagen';
                },

                // Bewaart de huidige stand. Loopt er al een opslag, dan volgt er direct
                // nog één, zodat wijzigingen van tijdens het opslaan niet wegvallen.
                save() {
                    clearTimeout(this.autosaveTimer);
                    if (this.saving) { this.saveQueued = true; return this.savePromise; }
                    this.saving = true;
                    this.saveError = '';
                    const snap = this.snapshot();
                    this.savePromise = (async () => {
                        try {
                            const response = await fetch(this.cfg.saveUrl, {
                                method: 'PUT',
                                headers: this.headers(),
                                body: JSON.stringify({ blocks: this.blocks }),
                            });
                            const data = await response.json().catch(() => null);
                            if (!response.ok || !data || !data.ok) {
                                throw new Error((data && (data.error || data.message)) || ('serverfout ' + response.status));
                            }
                            this.lastSaved = snap;
                            this.savedAt = new Date();
                        } catch (e) {
                            this.saveError = (e && e.message) ? e.message : 'geen verbinding';
                        }
                        this.saving = false;
                        this.dirty = this.snapshot() !== this.lastSaved;
                        if (this.saveQueued) {
                            this.saveQueued = false;
                            if (this.dirty && !this.saveError) await this.save();
                        }
                    })();
                    return this.savePromise;
                },

                headers() {
                    return {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    };
                },

                addBlock(type) {
                    if (this.isDisabled(type)) return;
                    const block = { id: this.newId(), type, data: JSON.parse(JSON.stringify(this.meta(type).default || {})) };
                    this.blocks.push(block);
                    this.selectedIdx = this.blocks.length - 1;
                    this.save();
                    this.flash(block.id);
                },
                removeBlock(index) {
                    if (this.isLocked(this.blocks[index].type)) return;
                    if (!confirm('Dit blok verwijderen?')) return;
                    this.blocks.splice(index, 1);
                    if (this.selectedIdx === index) this.selectedIdx = null;
                    this.save();
                },
                duplicateBlock(index) {
                    const block = this.blocks[index];
                    if (this.isSingleton(block.type)) return;
                    const copy = JSON.parse(JSON.stringify(block));
                    copy.id = this.newId();
                    this.blocks.splice(index + 1, 0, copy);
                    this.save();
                },
                dropBlock(toIndex) {
                    const from = this.dragFrom;
                    this.dragOver = null;
                    if (from === null || from === toIndex) return;
                    const [block] = this.blocks.splice(from, 1);
                    this.blocks.splice(toIndex, 0, block);
                    this.selectedIdx = toIndex;
                    this.dragFrom = null;
                    this.save();
                },
                toggleKeep(index) {
                    const block = this.blocks[index];
                    if (!block.data) block.data = {};
                    block.data._keep = !block.data._keep;
                    this.save();
                },
                flash(id) {
                    this.$nextTick(() => {
                        const el = document.querySelector(`[data-block-id="${id}"]`);
                        if (!el) return;
                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        el.classList.add('block-flash');
                        setTimeout(() => el.classList.remove('block-flash'), 1200);
                    });
                },

                async applyTemplate(tpl) {
                    if (this.blocks.length > 0 && !confirm(`Template "${tpl.name}" toepassen? Dit vervangt alle huidige blokken.`)) return;
                    try {
                        await this.save();
                        const response = await fetch(this.cfg.applyTplUrl, { method: 'POST', headers: this.headers(), body: JSON.stringify({ template_id: tpl.id }) });
                        const data = await response.json();
                        if (data.ok) {
                            this.blocks = data.blocks;
                            this.selectedIdx = null;
                            this.lastSaved = this.snapshot();
                            this.$nextTick(() => this.checkDirty());
                        } else {
                            alert(data.error || 'Kon de template niet toepassen.');
                        }
                    } catch { alert('Er ging iets mis bij het toepassen.'); }
                },

                openTplModal() {
                    if (this.blocks.length === 0) return;
                    this.tplModal = { open: true, mode: 'nieuw', template_id: '', name: '', description: '', saving: false, error: null };
                },
                async saveAsTemplate() {
                    if (this.tplModal.saving) return;
                    const isNew = this.tplModal.mode !== 'bestaand';
                    if (isNew ? !this.tplModal.name.trim() : !this.tplModal.template_id) return;
                    if (!isNew) {
                        const current = this.templates.find(tpl => tpl.id === this.tplModal.template_id);
                        if (!confirm(`De blokken van "${current ? current.name : 'deze template'}" vervangen door die van deze offerte?`)) return;
                    }
                    this.tplModal.saving = true;
                    this.tplModal.error = null;
                    try {
                        await this.save();
                        const response = await fetch(this.cfg.saveTplUrl, {
                            method: 'POST',
                            headers: this.headers(),
                            body: JSON.stringify(isNew
                                ? { name: this.tplModal.name.trim(), description: this.tplModal.description.trim() }
                                : { template_id: this.tplModal.template_id }),
                        });
                        const data = await response.json();
                        if (data.ok && data.template) {
                            const index = this.templates.findIndex(tpl => tpl.id === data.template.id);
                            if (index >= 0) this.templates[index] = data.template; else this.templates.push(data.template);
                            this.tplModal.open = false;
                        } else {
                            this.tplModal.error = data.error || 'Kon de template niet opslaan.';
                        }
                    } catch { this.tplModal.error = 'Er ging iets mis.'; }
                    this.tplModal.saving = false;
                },
                async deleteTemplate(tpl) {
                    if (!confirm(`Template "${tpl.name}" verwijderen?`)) return;
                    try {
                        const response = await fetch(`${this.cfg.deleteTplBase}/${tpl.id}`, { method: 'DELETE', headers: this.headers() });
                        if (response.ok) this.templates = this.templates.filter(item => item.id !== tpl.id);
                    } catch { /* volgende keer opnieuw */ }
                },

                // Upload voor foto-blokken; geeft een promise met de URL terug.
                async upload(file) {
                    if (!file) return null;
                    this.uploading = true;
                    const form = new FormData();
                    form.append('file', file);
                    try {
                        const response = await fetch(this.cfg.uploadUrl, {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                            body: form,
                        });
                        const data = await response.json().catch(() => null);
                        this.uploading = false;
                        if (response.ok && data && data.ok && data.url) return data.url;
                        let reason = (data && (data.error || data.message)) || ('HTTP ' + response.status);
                        if (data && data.errors) reason = Object.values(data.errors).flat().join(' ');
                        alert('Upload mislukt: ' + reason);
                    } catch (e) {
                        this.uploading = false;
                        alert('Upload mislukt: ' + ((e && e.message) ? e.message : 'netwerkfout'));
                    }
                    return null;
                },

                // Nova per blok
                openAi(index) {
                    this.ai = { open: true, index, prompt: '', loading: false, error: null };
                },
                aiLabel() {
                    const block = this.blocks[this.ai.index];
                    return block ? (this.meta(block.type).label || block.type) : '';
                },
                async runAi() {
                    const block = this.blocks[this.ai.index];
                    if (!block || this.ai.loading) return;
                    this.ai.loading = true;
                    this.ai.error = null;
                    try {
                        const response = await fetch(this.cfg.aiUrl, {
                            method: 'POST',
                            headers: this.headers(),
                            body: JSON.stringify({ type: block.type, data: block.data, instruction: this.ai.prompt }),
                        });
                        const data = await response.json();
                        if (data.ok) {
                            this.blocks[this.ai.index].data = data.data;
                            this.ai.open = false;
                            this.save();
                            this.flash(block.id);
                        } else {
                            this.ai.error = data.error || 'Er ging iets mis.';
                        }
                    } catch (e) { this.ai.error = (e && e.message) || 'Er ging iets mis.'; }
                    this.ai.loading = false;
                },

                // Nova bouwt de hele offerte (vastgezette blokken blijven staan)
                async generate() {
                    if (this.gen.loading) return;
                    if (this.blocks.length > 1) {
                        const pinned = this.blocks.filter(block => block.data && block.data._keep).length;
                        const message = pinned > 0
                            ? `Nova bouwt de offerte opnieuw. ${pinned} vastgezet blok${pinned === 1 ? '' : 'ken'} blijft staan, de rest wordt vervangen. Doorgaan?`
                            : 'Nova vervangt alle blokken (behalve de kop). Doorgaan?';
                        if (!confirm(message)) return;
                    }
                    this.gen.loading = true;
                    this.gen.error = null;
                    try {
                        await this.save();
                        const response = await fetch(this.cfg.generateUrl, {
                            method: 'POST',
                            headers: this.headers(),
                            body: JSON.stringify({ instruction: this.gen.prompt }),
                        });
                        const data = await response.json();
                        if (data.ok && data.blocks) {
                            this.blocks = data.blocks;
                            this.selectedIdx = null;
                            this.lastSaved = this.snapshot();
                            this.$nextTick(() => this.checkDirty());
                            document.querySelector('.canvas-scroll')?.scrollTo({ top: 0, behavior: 'smooth' });
                        } else {
                            this.gen.error = data.error || 'Nova kon geen offerte bouwen.';
                        }
                    } catch (e) { this.gen.error = (e && e.message) || 'Er ging iets mis.'; }
                    this.gen.loading = false;
                },

                // Dicteren via de browser (Web Speech API, nl-NL)
                toggleMic(target) {
                    const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                    if (!Recognition) { alert('Dicteren wordt niet ondersteund in deze browser.'); return; }
                    if (this.listening) { this.recognition?.stop(); return; }
                    const recognition = new Recognition();
                    recognition.lang = 'nl-NL';
                    recognition.continuous = true;
                    recognition.interimResults = false;
                    recognition.onresult = (event) => {
                        let text = '';
                        for (let i = event.resultIndex; i < event.results.length; i++) {
                            if (event.results[i].isFinal) text += event.results[i][0].transcript;
                        }
                        text = text.trim();
                        if (!text) return;
                        if (target === 'block') this.ai.prompt = (this.ai.prompt ? this.ai.prompt + ' ' : '') + text;
                        else this.gen.prompt = (this.gen.prompt ? this.gen.prompt + ' ' : '') + text;
                    };
                    recognition.onend = () => { this.listening = false; this.speechTarget = null; };
                    recognition.onerror = () => { this.listening = false; this.speechTarget = null; };
                    this.recognition = recognition;
                    this.speechTarget = target;
                    this.listening = true;
                    recognition.start();
                },
            }));
        });
    </script>
</body>
</html>
