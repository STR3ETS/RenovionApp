<x-layouts.portal :title="$project->name">

    <div x-data="{ tab: 'overzicht' }" class="space-y-4">

        {{-- Kop (mockup §18: % gereed + op schema) --}}
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
            @if ($project->cover_photo_path || $photos->isNotEmpty())
                <img src="{{ route('projects.cover', $project) }}" alt="{{ $project->name }}" class="h-40 w-full object-cover">
            @endif
            <div class="p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 class="text-lg font-bold text-navy-950">{{ $project->name }}</h1>
                        <p class="text-sm text-gray-500">{{ $project->customer->name }}</p>
                    </div>
                    <span class="flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold {{ $opSchema ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                        <x-signal-dot :color="$opSchema ? 'green' : 'amber'" /> {{ $opSchema ? 'Op schema' : 'Aandacht' }}
                    </span>
                </div>

                <div class="mt-4 flex items-end justify-between">
                    <p class="text-3xl font-bold text-navy-950">{{ $project->progress }}%<span class="ml-1 text-sm font-medium text-gray-500">gereed</span></p>
                    @php $volgende = $timeline->firstWhere('state', '!=', 'gereed'); @endphp
                    @if ($volgende)
                        <p class="text-xs text-gray-500">Nu bezig: <span class="font-semibold text-navy-900">{{ $volgende['label'] }}</span></p>
                    @endif
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full rounded-full bg-brand-500" style="width: {{ $project->progress }}%"></div>
                </div>

                {{-- Vereenvoudigde klant-tijdlijn. Smal: balk plus uitklapbare stappen, net als de interne fasebalk. --}}
                <div class="mt-5 md:hidden" x-data="{ open: false }">
                    <div class="flex gap-1" aria-hidden="true">
                        @foreach ($timeline as $stap)
                            <span class="h-2 flex-1 rounded-full {{ $stap['state'] === 'gereed' ? 'bg-green-500' : ($stap['state'] === 'bezig' ? 'bg-brand-500' : 'bg-gray-200') }}"></span>
                        @endforeach
                    </div>
                    <button type="button" @click="open = !open" :aria-expanded="open" class="tap mt-3 flex items-center gap-1 text-sm font-semibold text-brand-700">
                        <span x-text="open ? 'Stappen verbergen' : 'Alle stappen bekijken'">Alle stappen bekijken</span>
                        <x-icon name="chevron-down" class="h-4 w-4 transition" ::class="open && 'rotate-180'" />
                    </button>
                    <ol x-show="open" x-cloak x-transition.opacity class="mt-3 space-y-2">
                        @foreach ($timeline as $stap)
                            <li class="flex items-center gap-3">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold {{ $stap['state'] === 'gereed' ? 'bg-green-500 text-white' : ($stap['state'] === 'bezig' ? 'bg-brand-500 text-white' : 'bg-gray-200 text-gray-600') }}">
                                    @if ($stap['state'] === 'gereed')<x-icon name="check" class="h-3 w-3" />@else{{ $loop->iteration }}@endif
                                </span>
                                <span class="text-sm {{ $stap['state'] === 'bezig' ? 'font-bold text-navy-950' : ($stap['state'] === 'gereed' ? 'font-medium text-gray-600' : 'text-gray-500') }}">{{ $stap['label'] }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>

                <div class="mt-5 hidden md:block">
                    <ol class="flex items-start justify-center">
                        @foreach ($timeline as $stap)
                            <li class="flex items-start">
                                <div class="flex w-20 flex-col items-center gap-1.5 text-center">
                                    <span class="flex h-7 w-7 items-center justify-center rounded-full text-[11px] font-bold
                                        {{ $stap['state'] === 'gereed' ? 'bg-green-500 text-white' : ($stap['state'] === 'bezig' ? 'bg-brand-500 text-white ring-4 ring-brand-100' : 'bg-gray-200 text-gray-600') }}">
                                        @if ($stap['state'] === 'gereed')<x-icon name="check" class="h-3.5 w-3.5" />@else{{ $loop->iteration }}@endif
                                    </span>
                                    <span class="text-[10px] leading-tight font-semibold {{ $stap['state'] === 'bezig' ? 'text-navy-900' : 'text-gray-500' }}">{{ $stap['label'] }}</span>
                                </div>
                                @unless ($loop->last)
                                    <span class="mt-3.5 h-0.5 w-4 shrink-0 rounded-full {{ $stap['state'] === 'gereed' ? 'bg-green-400' : 'bg-gray-200' }}"></span>
                                @endunless
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </section>

        {{-- Tabs --}}
        <div class="flex gap-1 rounded-xl border border-gray-200 bg-white p-1 text-sm font-semibold">
            <button type="button" @click="tab = 'overzicht'" :class="tab === 'overzicht' ? 'bg-navy-950 text-white' : 'text-gray-600'" class="min-w-0 flex-1 rounded-lg px-2 py-2.5 transition">Overzicht</button>
            <button type="button" @click="tab = 'planning'" :class="tab === 'planning' ? 'bg-navy-950 text-white' : 'text-gray-600'" class="min-w-0 flex-1 rounded-lg px-2 py-2.5 transition">Planning</button>
            <button type="button" @click="tab = 'fotos'" :class="tab === 'fotos' ? 'bg-navy-950 text-white' : 'text-gray-600'" class="min-w-0 flex-1 rounded-lg px-2 py-2.5 transition">Foto's</button>
        </div>

        {{-- Tab: Overzicht --}}
        <div x-show="tab === 'overzicht'" class="space-y-4">
            {{-- Actie van u nodig --}}
            @if ($acties->isNotEmpty())
                @php
                    // Meerdere afgeronde fasen tegelijk: één kaart, anders drukken ze de rest van de pagina weg.
                    [$akkoorden, $overigeActies] = $acties->partition(fn ($actie) => $actie['type'] === 'akkoord');
                @endphp
                <section class="rounded-2xl border border-brand-200 bg-brand-50/60 p-5">
                    <h2 class="mb-3 text-sm font-bold text-navy-950">Actie van u nodig</h2>
                    <div class="space-y-3">
                        @foreach ($overigeActies as $actie)
                            <div class="rounded-xl border border-brand-100 bg-white p-4">
                                <p class="text-sm font-semibold text-navy-900">{{ $actie['title'] }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">{{ $actie['subtitle'] }}</p>
                                @if ($actie['type'] === 'offerte')
                                    <a href="{{ $actie['url'] }}" class="mt-3 inline-block rounded-xl bg-brand-500 px-5 py-2 text-sm font-bold text-white transition hover:bg-brand-600 active:bg-brand-600">Bekijk offerte</a>
                                @elseif ($actie['type'] === 'opleverrapport')
                                    <a href="{{ $actie['url'] }}" class="mt-3 inline-block rounded-xl bg-brand-500 px-5 py-2 text-sm font-bold text-white transition hover:bg-brand-600 active:bg-brand-600">Bekijk rapport</a>
                                @endif
                            </div>
                        @endforeach

                        @if ($akkoorden->count() === 1)
                            @php $actie = $akkoorden->first(); @endphp
                            <div class="rounded-xl border border-brand-100 bg-white p-4">
                                <p class="text-sm font-semibold text-navy-900">{{ $actie['title'] }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">{{ $actie['subtitle'] }}</p>
                                <form method="POST" action="{{ route('portal.phases.approve', $actie['phase']) }}" class="mt-3">
                                    @csrf
                                    <button type="submit" class="rounded-xl bg-brand-500 px-5 py-2 text-sm font-bold text-white transition hover:bg-brand-600 active:bg-brand-600">Gezien en akkoord</button>
                                </form>
                            </div>
                        @elseif ($akkoorden->isNotEmpty())
                            <div class="rounded-xl border border-brand-100 bg-white p-4" x-data="{ open: false }">
                                <p class="text-sm font-semibold text-navy-900">{{ $akkoorden->count() }} afgeronde fasen om te bevestigen</p>
                                <p class="mt-0.5 text-xs text-gray-500">Bevestig dat u deze fasen heeft gezien. Dat kan in één keer of per fase.</p>
                                <form method="POST" action="{{ route('portal.phases.approve-all', $project) }}" class="mt-3">
                                    @csrf
                                    <button type="submit" class="w-full rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600 active:bg-brand-600">Alles gezien en akkoord</button>
                                </form>
                                <button type="button" @click="open = !open" :aria-expanded="open" class="tap mt-3 flex items-center gap-1 text-sm font-semibold text-brand-700">
                                    <span x-text="open ? 'Fasen verbergen' : 'Per fase bekijken'">Per fase bekijken</span>
                                    <x-icon name="chevron-down" class="h-4 w-4 transition" ::class="open && 'rotate-180'" />
                                </button>
                                <ul x-show="open" x-cloak class="mt-2 divide-y divide-gray-100">
                                    @foreach ($akkoorden as $actie)
                                        <li class="flex items-center justify-between gap-3 py-2">
                                            <span class="min-w-0 text-sm font-medium text-navy-900">{{ $actie['phase']->name }}</span>
                                            <form method="POST" action="{{ route('portal.phases.approve', $actie['phase']) }}" class="shrink-0">
                                                @csrf
                                                <button type="submit" class="tap rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50">Akkoord</button>
                                            </form>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            {{-- Deze week --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-navy-950">Deze week</h2>
                @forelse ($dezeWeek as $entry)
                    <div class="mb-2 flex items-center gap-3 rounded-xl border border-gray-200 p-3 text-sm">
                        <span class="w-16 shrink-0 text-xs font-bold text-navy-900">{{ $entry->date->isToday() ? 'Vandaag' : $entry->date->translatedFormat('D j M') }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-navy-900">{{ $entry->displayTitle() }}</span>
                            <span class="block text-xs text-gray-500">{{ $entry->user->name }}{{ $entry->start_time ? ', vanaf '.substr($entry->start_time, 0, 5) : '' }}</span>
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Voor de komende week staat er (nog) niets gepland.</p>
                @endforelse
            </section>

            {{-- Laatste foto's --}}
            @if ($photos->isNotEmpty())
                <section class="rounded-2xl border border-gray-200 bg-white p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-navy-950">Laatste foto's</h2>
                        <button type="button" @click="tab = 'fotos'" class="tap text-xs font-semibold text-brand-700 hover:text-brand-500">Alle foto's →</button>
                    </div>
                    <div class="grid grid-cols-4 gap-2">
                        @foreach ($photos->take(4) as $photo)
                            <button type="button" class="block cursor-zoom-in" title="Foto bekijken" data-photo="{{ route('photos.show', $photo) }}" data-caption="{{ $photo->caption ?? $photo->phase?->name ?? 'Projectfoto' }}" data-meta="{{ $photo->created_at->translatedFormat('j F Y') }}">
                                <img src="{{ route('photos.show', $photo) }}" alt="{{ $photo->caption ?? 'Projectfoto' }}" loading="lazy" class="h-20 w-full rounded-lg object-cover">
                            </button>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        {{-- Tab: Planning --}}
        <div x-show="tab === 'planning'" x-cloak>
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-navy-950">Komende week</h2>
                @forelse ($dezeWeek as $entry)
                    <div class="mb-2 flex items-center gap-3 rounded-xl border border-gray-200 p-3 text-sm">
                        <span class="w-16 shrink-0 text-xs font-bold text-navy-900">{{ $entry->date->isToday() ? 'Vandaag' : $entry->date->translatedFormat('D j M') }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-navy-900">{{ $entry->displayTitle() }}</span>
                            <span class="block text-xs text-gray-500">{{ $entry->user->name }}{{ $entry->start_time ? ', vanaf '.substr($entry->start_time, 0, 5) : '' }}</span>
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Er staat voor de komende week niets gepland.</p>
                @endforelse
                @if ($project->end_date_expected)
                    <p class="mt-3 text-xs text-gray-500">Verwachte oplevering: <span class="font-semibold text-navy-900">{{ $project->end_date_expected->translatedFormat('j F Y') }}</span></p>
                @endif
            </section>
        </div>

        {{-- Tab: Foto's --}}
        <div x-show="tab === 'fotos'" x-cloak>
            @if ($photos->isEmpty())
                <section class="rounded-2xl border border-gray-200 bg-white p-8 text-center text-sm text-gray-500">Zodra er foto's van uw project zijn, verschijnen ze hier.</section>
            @else
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ($photos as $photo)
                        <figure class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                            <button type="button" class="block w-full cursor-zoom-in text-left" title="Foto bekijken" data-photo="{{ route('photos.show', $photo) }}" data-caption="{{ $photo->caption ?? $photo->phase?->name ?? 'Projectfoto' }}" data-meta="{{ $photo->created_at->translatedFormat('j F Y') }}">
                                <img src="{{ route('photos.show', $photo) }}" alt="{{ $photo->caption ?? 'Projectfoto' }}" loading="lazy" class="h-36 w-full object-cover">
                                <span class="block px-3 py-2">
                                    <span class="block truncate text-xs font-semibold text-navy-900">{{ $photo->caption ?? $photo->phase?->name ?? 'Projectfoto' }}</span>
                                    <span class="block text-[11px] text-gray-500">{{ $photo->created_at->translatedFormat('j M Y') }}</span>
                                </span>
                            </button>
                        </figure>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

</x-layouts.portal>
