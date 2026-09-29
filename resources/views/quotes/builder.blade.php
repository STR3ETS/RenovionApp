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
        .edit-field { outline: none; min-height: 1.2em; }
        .edit-field:focus { background: rgba(248, 91, 11, 0.06); border-radius: 6px; box-shadow: 0 0 0 4px rgba(248, 91, 11, 0.06); }
        .edit-field:empty:before { content: attr(data-ph); color: rgb(156 163 175); pointer-events: none; }
        .block-wrapper { position: relative; outline: 2px solid transparent; outline-offset: 6px; border-radius: 16px; transition: outline-color .15s; }
        .block-wrapper:hover { outline-color: rgba(248, 91, 11, .25); }
        .block-actions { position: absolute; top: 50%; right: -46px; transform: translateY(-50%); display: flex; flex-direction: column; gap: 4px; opacity: 0; transition: opacity .15s; z-index: 20; }
        .block-wrapper:hover .block-actions { opacity: 1; }
    </style>
</head>
<body class="h-screen overflow-hidden bg-gray-100 font-sans text-gray-900 antialiased"
      x-data="quoteBuilder({{ Js::from([
          'saveUrl' => route('quotes.builder.update', $quote),
          'applyTplUrl' => route('quotes.builder.template', $quote),
          'saveTplUrl' => route('quotes.builder.save-template', $quote),
          'deleteTplBase' => url('/offerte-templates'),
          'uploadUrl' => route('quotes.builder.upload', $quote),
          'aiUrl' => route('quotes.builder.ai', $quote),
          'blocks' => $quote->blocks ?? [],
          'registry' => $registry,
          'templates' => $templates->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'description' => $t->description, 'is_default' => (bool) $t->is_default])->all(),
      ]) }})">

    {{-- Topbar --}}
    <header class="flex h-14 items-center gap-3 bg-navy-950 px-4 text-white">
        <a href="{{ route('quotes.show', $quote) }}" class="flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-semibold text-navy-200 transition hover:bg-navy-900 hover:text-white">
            <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> Terug
        </a>
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-bold">Offerte bouwen — {{ $quote->number }} · v{{ $quote->version }}</p>
            <p class="truncate text-xs text-navy-300">{{ $quote->customer->name }}{{ $quote->lead?->service ? ' · '.$quote->lead->service : '' }}</p>
        </div>
        <span class="text-xs font-semibold" :class="saving ? 'text-navy-300' : 'text-green-400'" x-text="saving ? 'Opslaan…' : (saved ? 'Opgeslagen' : '')"></span>
        <a href="{{ route('quotes.show', $quote) }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-600">Klaar</a>
    </header>

    <div class="flex h-[calc(100vh-3.5rem)]">

        {{-- Linkerbalk: blokken + templates --}}
        <aside class="hidden w-72 shrink-0 flex-col border-r border-gray-200 bg-white lg:flex">
            <div class="flex gap-1 p-3 pb-0 text-sm font-semibold">
                <button type="button" @click="rail = 'blokken'" :class="rail === 'blokken' ? 'bg-navy-950 text-white' : 'text-gray-600 hover:bg-gray-100'" class="flex-1 rounded-lg px-3 py-2 transition">Blokken</button>
                <button type="button" @click="rail = 'templates'" :class="rail === 'templates' ? 'bg-navy-950 text-white' : 'text-gray-600 hover:bg-gray-100'" class="flex-1 rounded-lg px-3 py-2 transition">Templates</button>
            </div>

            {{-- Blokbibliotheek --}}
            <div x-show="rail === 'blokken'" class="flex-1 space-y-1.5 overflow-y-auto p-3">
                <template x-for="item in registry.filter(r => !r.locked)" :key="item.type">
                    <button type="button" @click="addBlock(item.type)"
                            :disabled="item.singleton && hasBlock(item.type)"
                            class="flex w-full items-center gap-3 rounded-xl border border-gray-200 p-3 text-left transition hover:border-brand-400 disabled:cursor-not-allowed disabled:opacity-40">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-500">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke="currentColor"><use :href="'#icon-' + item.type"></use></svg>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-navy-900" x-text="item.label"></span>
                            <span class="block truncate text-xs text-gray-400" x-text="item.description"></span>
                        </span>
                    </button>
                </template>
                <p class="pt-2 text-xs text-gray-400">Klik op een blok om het onderaan toe te voegen. Tekst pas je direct in het document aan.</p>
            </div>

            {{-- Templates --}}
            <div x-show="rail === 'templates'" x-cloak class="flex-1 space-y-1.5 overflow-y-auto p-3">
                <template x-for="tpl in templates" :key="tpl.id">
                    <div class="group flex items-center gap-2 rounded-xl border border-gray-200 p-3 transition hover:border-brand-400">
                        <button type="button" @click="applyTemplate(tpl)" class="min-w-0 flex-1 text-left">
                            <span class="block text-sm font-semibold text-navy-900" x-text="tpl.name"></span>
                            <span class="block truncate text-xs text-gray-400" x-text="tpl.description"></span>
                        </button>
                        <button type="button" x-show="!tpl.is_default" @click="deleteTemplate(tpl)"
                                class="rounded p-1 text-gray-300 opacity-0 transition group-hover:opacity-100 hover:text-red-500" title="Template verwijderen">
                            <x-icon name="trash" class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </template>

                <div class="mt-3 space-y-2 rounded-xl border border-dashed border-gray-300 p-3">
                    <p class="text-xs font-bold text-navy-900">Huidige offerte als template</p>
                    <input type="text" x-model="newTplName" maxlength="120" placeholder="Naam (bijv. Badkamer luxe)"
                           class="w-full rounded-lg border-gray-300 text-xs focus:border-brand-500 focus:ring-brand-500">
                    <button type="button" @click="saveAsTemplate()" :disabled="!newTplName.trim() || tplSaving"
                            class="w-full rounded-lg bg-gray-100 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-200 disabled:opacity-40">
                        <span x-text="tplSaving ? 'Opslaan…' : 'Opslaan als template'"></span>
                    </button>
                </div>
            </div>
        </aside>

        {{-- Canvas: het live document --}}
        <main class="flex-1 overflow-y-auto" @input.debounce.1500ms="save()">
            <div class="mx-auto max-w-3xl px-6 py-8 lg:px-10">
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm lg:p-8">
                    <div class="space-y-8">
                        <template x-for="(block, index) in blocks" :key="block.id + ':' + rev">
                            <div class="block-wrapper" :data-block-id="block.id">

                                {{-- Zwevende blokacties --}}
                                <div class="block-actions">
                                    <button type="button" @click="move(index, -1)" :disabled="index === 0" class="flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 shadow-sm transition hover:bg-navy-950 hover:text-white disabled:opacity-30" title="Omhoog">↑</button>
                                    <button type="button" @click="move(index, 1)" :disabled="index === blocks.length - 1" class="flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 shadow-sm transition hover:bg-navy-950 hover:text-white disabled:opacity-30" title="Omlaag">↓</button>
                                    <button type="button" @click="openAi(index)" class="flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 bg-white shadow-sm transition hover:bg-brand-500 hover:text-white" title="Laat Nova dit blok schrijven">
                                        <img src="{{ asset('nova.jpg') }}" alt="Nova" class="h-5 w-5 rounded-full object-cover">
                                    </button>
                                    <button type="button" x-show="!isLocked(block.type)" @click="removeBlock(index)" class="flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 shadow-sm transition hover:bg-red-500 hover:text-white" title="Verwijderen">
                                        <x-icon name="trash" class="h-3.5 w-3.5" />
                                    </button>
                                </div>

                                {{-- Kop --}}
                                <template x-if="block.type === 'hero'">
                                    <header class="overflow-hidden rounded-2xl bg-navy-950 p-8 text-white lg:p-10">
                                        <img src="{{ asset('images/renovion-logo.svg') }}" alt="Renovion" class="mb-6 h-9 w-auto">
                                        <p class="text-sm text-navy-300"><span class="edit-field" x-edit="block.data.title" data-ph="Offerte"></span> {{ $quote->number }} · versie v{{ $quote->version }}</p>
                                        <h1 class="edit-field mt-1 text-2xl font-bold lg:text-3xl" x-edit="block.data.subtitle" data-ph="Type werk"></h1>
                                        <p class="mt-1 text-navy-100">{{ $quote->customer->name }}</p>
                                        @if ($quote->valid_until)
                                            <p class="mt-4 inline-block rounded-full bg-navy-900 px-3 py-1 text-xs font-semibold text-navy-200">Geldig tot {{ $quote->valid_until->translatedFormat('j F Y') }}</p>
                                        @endif
                                    </header>
                                </template>

                                {{-- Tekst --}}
                                <template x-if="block.type === 'text'">
                                    <section>
                                        <h2 class="edit-field mb-3 text-lg font-bold text-navy-950" x-edit="block.data.title" data-ph="Titel"></h2>
                                        <p class="edit-field text-sm whitespace-pre-line text-gray-600" x-edit="block.data.body" data-ph="Schrijf hier de tekst…"></p>
                                    </section>
                                </template>

                                {{-- Lijst --}}
                                <template x-if="block.type === 'list'">
                                    <section>
                                        <h2 class="edit-field mb-3 text-lg font-bold text-navy-950" x-edit="block.data.title" data-ph="Titel"></h2>
                                        <p class="edit-field mb-3 text-sm whitespace-pre-line text-gray-600" x-edit="block.data.intro" data-ph="Korte inleiding (optioneel)"></p>
                                        <ul class="space-y-1.5">
                                            <template x-for="(item, i) in block.data.items" :key="block.id + '-item-' + i">
                                                <li class="group/item flex items-start gap-2 text-sm text-gray-600">
                                                    <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-brand-500" />
                                                    <span class="edit-field min-w-0 flex-1 whitespace-pre-line" x-edit="block.data.items[i]" data-ph="Punt…"></span>
                                                    <button type="button" @click="block.data.items.splice(i, 1); bump()" class="text-gray-300 opacity-0 transition group-hover/item:opacity-100 hover:text-red-500" title="Punt verwijderen"><x-icon name="x-mark" class="h-3.5 w-3.5" /></button>
                                                </li>
                                            </template>
                                        </ul>
                                        <button type="button" @click="block.data.items.push('Nieuw punt'); bump()" class="mt-2 text-xs font-semibold text-brand-600 hover:text-brand-500">+ Punt toevoegen</button>
                                    </section>
                                </template>

                                {{-- Fasering --}}
                                <template x-if="block.type === 'phases'">
                                    <section>
                                        <h2 class="edit-field mb-3 text-lg font-bold text-navy-950" x-edit="block.data.title" data-ph="Fasering"></h2>
                                        <p class="edit-field mb-4 text-sm whitespace-pre-line text-gray-600" x-edit="block.data.intro" data-ph="Korte inleiding (optioneel)"></p>
                                        <ol class="space-y-3">
                                            <template x-for="(fase, i) in block.data.items" :key="block.id + '-fase-' + i">
                                                <li class="group/item flex gap-3">
                                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-500 text-xs font-bold text-white" x-text="i + 1"></span>
                                                    <span class="min-w-0 flex-1">
                                                        <span class="edit-field block text-sm font-bold text-navy-900" x-edit="block.data.items[i].title" data-ph="Fasenaam"></span>
                                                        <span class="edit-field block text-sm whitespace-pre-line text-gray-600" x-edit="block.data.items[i].body" data-ph="Wat gebeurt er in deze fase…"></span>
                                                    </span>
                                                    <button type="button" @click="block.data.items.splice(i, 1); bump()" class="text-gray-300 opacity-0 transition group-hover/item:opacity-100 hover:text-red-500" title="Fase verwijderen"><x-icon name="x-mark" class="h-3.5 w-3.5" /></button>
                                                </li>
                                            </template>
                                        </ol>
                                        <button type="button" @click="block.data.items.push({ title: 'Nieuwe fase', body: '' }); bump()" class="mt-2 text-xs font-semibold text-brand-600 hover:text-brand-500">+ Fase toevoegen</button>
                                    </section>
                                </template>

                                {{-- Investering --}}
                                <template x-if="block.type === 'investment'">
                                    <section>
                                        <h2 class="edit-field mb-3 text-lg font-bold text-navy-950" x-edit="block.data.title" data-ph="Investering"></h2>
                                        <p class="edit-field mb-4 text-sm whitespace-pre-line text-gray-600" x-edit="block.data.intro" data-ph="Korte inleiding (optioneel)"></p>
                                        <div class="overflow-hidden rounded-xl border border-gray-200">
                                            <table class="w-full text-sm">
                                                <tbody>
                                                    @foreach ($quote->lines as $line)
                                                        <tr class="border-b border-gray-100 {{ $line->is_estimate ? 'bg-gray-50/60' : '' }}">
                                                            <td class="px-4 py-3 font-medium text-navy-900">
                                                                {{ $line->description }}
                                                                @if ($line->is_estimate)<span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800">stelpost</span>@endif
                                                            </td>
                                                            <td class="px-4 py-3 text-right font-semibold whitespace-nowrap text-navy-900">€ {{ number_format((float) $line->total, 2, ',', '.') }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot class="bg-gray-50">
                                                    <tr><td class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">Subtotaal excl. btw</td><td class="px-4 py-2.5 text-right font-semibold whitespace-nowrap text-navy-900">€ {{ number_format((float) $quote->subtotal, 2, ',', '.') }}</td></tr>
                                                    <tr><td class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">Btw</td><td class="px-4 py-2.5 text-right font-semibold whitespace-nowrap text-navy-900">€ {{ number_format((float) $quote->vat_amount, 2, ',', '.') }}</td></tr>
                                                    <tr class="border-t-2 border-navy-950/10"><td class="px-4 py-3 text-right text-sm font-bold text-navy-950">Totaal incl. btw</td><td class="px-4 py-3 text-right text-base font-bold whitespace-nowrap text-brand-600">€ {{ number_format((float) $quote->total, 2, ',', '.') }}</td></tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                        <p class="edit-field mt-3 text-xs text-gray-500" x-edit="block.data.note" data-ph="Notitie onder de tabel (bijv. over stelposten)"></p>
                                        <p class="mt-2 text-[11px] text-gray-400">De posten en bedragen bewerk je via "Posten bewerken" op de offertepagina.</p>
                                    </section>
                                </template>

                                {{-- FAQ --}}
                                <template x-if="block.type === 'faq'">
                                    <section>
                                        <h2 class="edit-field mb-3 text-lg font-bold text-navy-950" x-edit="block.data.title" data-ph="Veelgestelde vragen"></h2>
                                        <div class="space-y-3">
                                            <template x-for="(item, i) in block.data.items" :key="block.id + '-faq-' + i">
                                                <div class="group/item">
                                                    <div class="flex items-start gap-2">
                                                        <p class="edit-field min-w-0 flex-1 text-sm font-bold text-navy-900" x-edit="block.data.items[i].q" data-ph="Vraag?"></p>
                                                        <button type="button" @click="block.data.items.splice(i, 1); bump()" class="text-gray-300 opacity-0 transition group-hover/item:opacity-100 hover:text-red-500" title="Vraag verwijderen"><x-icon name="x-mark" class="h-3.5 w-3.5" /></button>
                                                    </div>
                                                    <p class="edit-field text-sm whitespace-pre-line text-gray-600" x-edit="block.data.items[i].a" data-ph="Antwoord…"></p>
                                                </div>
                                            </template>
                                        </div>
                                        <button type="button" @click="block.data.items.push({ q: 'Nieuwe vraag?', a: '' }); bump()" class="mt-2 text-xs font-semibold text-brand-600 hover:text-brand-500">+ Vraag toevoegen</button>
                                    </section>
                                </template>

                                {{-- Foto --}}
                                <template x-if="block.type === 'image'">
                                    <figure>
                                        <template x-if="block.data.url">
                                            <img :src="block.data.url" class="w-full rounded-2xl object-cover" alt="">
                                        </template>
                                        <template x-if="!block.data.url">
                                            <label class="flex h-40 w-full cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-gray-300 text-sm font-semibold text-gray-400 transition hover:border-brand-400 hover:text-brand-500">
                                                <x-icon name="camera" class="h-6 w-6" />
                                                <span x-text="uploadingId === block.id ? 'Uploaden…' : 'Klik om een foto te kiezen'"></span>
                                                <input type="file" accept="image/*" class="hidden" @change="uploadImage($event, block)">
                                            </label>
                                        </template>
                                        <div class="mt-2 flex items-center justify-center gap-3">
                                            <figcaption class="edit-field text-center text-xs text-gray-400" x-edit="block.data.caption" data-ph="Bijschrift (optioneel)"></figcaption>
                                            <button type="button" x-show="block.data.url" @click="block.data.url = ''; bump()" class="text-[11px] font-semibold text-gray-400 hover:text-red-500">Andere foto</button>
                                        </div>
                                    </figure>
                                </template>

                                {{-- Scheiding --}}
                                <template x-if="block.type === 'divider'">
                                    <hr class="border-gray-200">
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
                <p class="py-4 text-center text-xs text-gray-400">Wijzigingen worden automatisch opgeslagen. Beweeg over een blok voor verplaatsen, Nova of verwijderen.</p>
            </div>
        </main>
    </div>

    {{-- Nova per-blok AI --}}
    <div x-show="ai.open" x-cloak @keydown.escape.window="ai.open = false"
         class="fixed inset-0 z-50 flex items-center justify-center bg-navy-950/60 p-4 backdrop-blur-sm" @click.self="ai.open = false">
        <div class="w-full max-w-md rounded-2xl bg-white p-5">
            <div class="mb-3 flex items-center gap-3">
                <img src="{{ asset('nova.jpg') }}" alt="Nova" class="h-9 w-9 rounded-full object-cover ring-2 ring-brand-500">
                <div>
                    <p class="text-sm font-bold text-navy-900">Nova schrijft dit blok</p>
                    <p class="text-xs text-gray-400">Jij controleert het resultaat in het document.</p>
                </div>
            </div>
            <textarea x-model="ai.prompt" rows="3" placeholder="Wat wil je? Bijv. 'maak dit persoonlijker en noem de start in november' — of laat leeg voor een voorstel op basis van de klantgegevens."
                      class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
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

    {{-- Iconen voor de blokbibliotheek --}}
    <svg class="hidden">
        <symbol id="icon-text"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></symbol>
        <symbol id="icon-list"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></symbol>
        <symbol id="icon-phases"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></symbol>
        <symbol id="icon-investment"><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 7.756a4.5 4.5 0 1 0 0 8.488M7.5 10.5h5.25m-5.25 3h5.25M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></symbol>
        <symbol id="icon-faq"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></symbol>
        <symbol id="icon-image"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></symbol>
        <symbol id="icon-divider"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5" /></symbol>
    </svg>

    <script>
        document.addEventListener('alpine:init', () => {
            // Inline bewerken: contenteditable dat terugschrijft naar block.data
            // zonder her-render (dus zonder cursorverlies).
            Alpine.directive('edit', (el, { expression }, { evaluateLater }) => {
                const getValue = evaluateLater(expression);
                const setValue = evaluateLater(`${expression} = __editValue`);
                getValue((value) => { el.textContent = value ?? ''; });
                el.setAttribute('contenteditable', 'plaintext-only');
                if (!el.isContentEditable) el.setAttribute('contenteditable', 'true');
                el.addEventListener('input', () => {
                    setValue(() => {}, { scope: { __editValue: el.innerText.replace(/\n$/, '') } });
                });
            });

            Alpine.data('quoteBuilder', (cfg) => ({
                cfg,
                blocks: cfg.blocks,
                registry: cfg.registry,
                templates: cfg.templates,
                rail: 'blokken',
                rev: 0,
                saving: false,
                saved: false,
                uploadingId: null,
                newTplName: '',
                tplSaving: false,
                ai: { open: false, index: null, prompt: '', loading: false, error: null },

                isLocked(type) {
                    return (this.registry.find(r => r.type === type) || {}).locked === true;
                },
                hasBlock(type) {
                    return this.blocks.some(b => b.type === type);
                },
                bump() {
                    this.rev++;
                    this.save();
                },

                addBlock(type) {
                    const meta = this.registry.find(r => r.type === type);
                    if (!meta || (meta.singleton && this.hasBlock(type))) return;
                    const block = { id: 'b_' + Math.random().toString(36).slice(2, 14), type, data: JSON.parse(JSON.stringify(meta.default || {})) };
                    this.blocks.push(block);
                    this.bump();
                    this.$nextTick(() => document.querySelector(`[data-block-id="${block.id}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
                },
                removeBlock(index) {
                    if (!confirm('Dit blok verwijderen?')) return;
                    this.blocks.splice(index, 1);
                    this.bump();
                },
                move(index, delta) {
                    const target = index + delta;
                    if (target < 0 || target >= this.blocks.length) return;
                    const [block] = this.blocks.splice(index, 1);
                    this.blocks.splice(target, 0, block);
                    this.bump();
                },

                async save() {
                    this.saving = true;
                    try {
                        const response = await fetch(this.cfg.saveUrl, {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                            body: JSON.stringify({ blocks: this.blocks }),
                        });
                        this.saved = response.ok;
                    } catch { this.saved = false; }
                    this.saving = false;
                },

                async applyTemplate(tpl) {
                    if (!confirm(`Template "${tpl.name}" toepassen? De huidige blokken worden vervangen.`)) return;
                    const data = await postJson(this.cfg.applyTplUrl, { template_id: tpl.id }).catch((e) => { alert(e.message); return null; });
                    if (data && data.ok) { this.blocks = data.blocks; this.rev++; this.saved = true; }
                },
                async saveAsTemplate() {
                    this.tplSaving = true;
                    try {
                        const data = await postJson(this.cfg.saveTplUrl, { name: this.newTplName.trim() });
                        if (data.ok) { this.templates.push(data.template); this.newTplName = ''; }
                    } catch (e) { alert(e.message); }
                    this.tplSaving = false;
                },
                async deleteTemplate(tpl) {
                    if (!confirm(`Template "${tpl.name}" verwijderen?`)) return;
                    try {
                        const response = await fetch(`${this.cfg.deleteTplBase}/${tpl.id}`, {
                            method: 'DELETE',
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        });
                        if (response.ok) this.templates = this.templates.filter(t => t.id !== tpl.id);
                    } catch { /* volgende keer opnieuw */ }
                },

                async uploadImage(event, block) {
                    const file = event.target.files[0];
                    if (!file) return;
                    this.uploadingId = block.id;
                    const form = new FormData();
                    form.append('file', file);
                    try {
                        const response = await fetch(this.cfg.uploadUrl, {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                            body: form,
                        });
                        const data = await response.json();
                        if (data.ok) { block.data.url = data.url; this.bump(); }
                        else alert(data.error || 'Upload mislukt.');
                    } catch { alert('Upload mislukt.'); }
                    this.uploadingId = null;
                },

                openAi(index) {
                    this.ai = { open: true, index, prompt: '', loading: false, error: null };
                },
                async runAi() {
                    const block = this.blocks[this.ai.index];
                    if (!block || this.ai.loading) return;
                    this.ai.loading = true;
                    this.ai.error = null;
                    try {
                        const data = await postJson(this.cfg.aiUrl, { type: block.type, data: block.data, instruction: this.ai.prompt });
                        if (data.ok) {
                            block.data = data.data;
                            this.ai.open = false;
                            this.bump();
                        } else {
                            this.ai.error = data.error || 'Er ging iets mis.';
                        }
                    } catch (e) {
                        this.ai.error = e.message;
                    }
                    this.ai.loading = false;
                },
            }));
        });
    </script>
</body>
</html>
