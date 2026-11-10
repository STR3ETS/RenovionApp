@props(['title' => null])

@php
    // De iOS/Android-app heeft een eigen schil (partials/app) en eigen animaties (motion-head/-stage op
    // html[data-motion]); de website gebruikt de donkere schil en de animatielaag op body[data-motion].
    $isApp = \App\Support\NativeApp::active();
    $motionOn = auth()->user()?->motion_enabled !== false;
@endphp

<!DOCTYPE html>
<html lang="nl" @if ($isApp) class="native-app" data-sec="{{ \App\Support\AppSection::current()['key'] ?? 'vandaag' }}" @if ($motionOn) data-motion @endif @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- De iOS/Android-app meldt hier het Firebase-token aan voor pushmeldingen (native.js). --}}
    <meta name="push-register" content="{{ route('devices.store') }}" data-user="{{ auth()->id() }}">
    @if ($isApp)
        {{-- Snel wisselen in de app (Turbo, native.js) vervangt <html> niet: de sectiekleur komt hiervandaan. --}}
        <meta name="app-section" content="{{ \App\Support\AppSection::current()['key'] ?? 'vandaag' }}">
    @endif
    <title>{{ $title ? $title.' - ' : '' }}{{ $isApp ? 'Renovion OS' : config('app.name') }}</title>
    @include('partials.favicon')

    @include('partials.fonts')

    @include('partials.load-guard')
    @if ($isApp)
        @include('partials.motion-head')
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Chrome laadt pagina's alvast bij hover: navigatie voelt direct (stijlgids §08).
         Chat (polling) en de offerte-builder (autosave) worden niet vooraf geladen. --}}
    <script type="speculationrules">
    {
        "prerender": [{
            "where": { "and": [
                { "href_matches": "/*" },
                { "not": { "href_matches": "/chat*" } },
                { "not": { "href_matches": "/meldingen*" } },
                { "not": { "href_matches": "/offertes/*/builder*" } }
            ]},
            "eagerness": "moderate"
        }]
    }
    </script>
</head>
<body class="app-shell min-h-screen bg-surface-900 font-sans text-slate-200 antialiased"
      data-motion="{{ $motionOn && ! $isApp ? 'on' : 'off' }}">

    @php
        $canCrm = auth()->user()->can('manage-crm');
        $aandachtCount = $canCrm ? \App\Services\AttentionService::cachedCount() : 0;
        $chatUnread = \App\Models\ChatChannel::unreadTotalFor(auth()->user());
        // "Aan de slag" staat bovenaan het menu tot de gebruiker het zelf weghaalt; daarna via het sterretje bij je naam.
        $onboarding = app(\App\Services\OnboardingGuide::class)->summaryFor(auth()->user());
        $onboardingItem = $onboarding['dismissed'] ? null : ['label' => 'Aan de slag', 'icon' => 'sparkles', 'route' => 'onboarding', 'active' => request()->routeIs('onboarding'), 'badge' => $onboarding['open'], 'badgeTone' => 'brand'];
    @endphp

    @php
        // Het menu komt uit één bron: App\Support\Navigation (secties, tabbladen, rechten).
        $sections = \App\Support\Navigation::forUser(auth()->user());
        $sectionTabs = \App\Support\Navigation::tabsFor(auth()->user());
        $counters = ['inbox' => $aandachtCount, 'chat' => $chatUnread];
    @endphp

    @unless ($isApp)
    {{-- Desktop sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-white/5 bg-surface-950 text-white lg:flex">
        <div class="px-6 pt-6 pb-4">
            <a href="{{ route('dashboard') }}" class="block">
                <img src="{{ asset('images/renovion-logo.svg') }}" alt="Renovion" class="h-11 w-auto">
                <p class="mt-1.5 text-[9px] font-bold tracking-[0.18em] text-slate-500 uppercase">Een platform, hele bouwprojecten</p>
            </a>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 pb-6">
            @if ($onboardingItem)
                <p class="px-3 pt-5 pb-2 text-[10px] font-bold tracking-widest text-slate-500 uppercase">Start</p>
                <a href="{{ route('onboarding') }}"
                   class="flex items-center gap-3 rounded-full px-3 py-2.5 text-sm font-medium transition {{ $onboardingItem['active'] ? 'bg-brand-500 text-white shadow-lg shadow-brand-500/20' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                    <x-icon name="sparkles" class="h-5 w-5 shrink-0 {{ $onboardingItem['active'] ? 'text-white' : 'text-slate-400' }}" />
                    <span class="flex-1 truncate">Aan de slag</span>
                    @if ($onboardingItem['badge'] > 0)
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold text-white {{ $onboardingItem['active'] ? 'bg-white/20' : 'bg-brand-500' }}">{{ $onboardingItem['badge'] }}</span>
                    @endif
                </a>
            @endif

            @foreach (\App\Support\Navigation::GROUPS as $group)
                @continue(empty($sections[$group]))
                <p class="px-3 pt-5 pb-2 text-[10px] font-bold tracking-widest text-slate-500 uppercase">{{ $group }}</p>
                <div class="space-y-1">
                    @foreach ($sections[$group] as $item)
                        @php($badge = $item['counter'] ? ($counters[$item['counter']] ?? 0) : 0)
                        <a href="{{ $item['url'] }}"
                           class="flex items-center gap-3 rounded-full px-3 py-2.5 text-sm font-medium transition {{ $item['active'] ? 'bg-brand-500 text-white shadow-lg shadow-brand-500/20' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                            <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0 {{ $item['active'] ? 'text-white' : 'text-slate-400' }}" />
                            <span class="flex-1 truncate">{{ $item['label'] }}</span>
                            @if ($badge > 0)
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold text-white {{ $item['active'] ? 'bg-white/20' : ($item['counter'] === 'inbox' ? 'bg-brand-500' : 'bg-white/10') }}">{{ $badge }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>

        <div class="border-t border-white/5 p-4">
            <div class="flex items-center justify-between gap-2">
                <div class="flex min-w-0 items-center gap-2.5">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/10 text-xs font-bold text-slate-200">
                        {{ str(auth()->user()->name)->substr(0, 1)->upper() }}
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold">{{ auth()->user()->name }}</span>
                        <span class="block truncate text-xs text-slate-400">{{ auth()->user()->role->label() }}</span>
                    </span>
                </div>
                <div class="flex shrink-0 items-center">
                    <a href="{{ route('onboarding') }}" class="rounded-lg p-2 text-slate-400 transition hover:bg-white/5 hover:text-white" title="Aan de slag: zo werkt het systeem">
                        <x-icon name="sparkles" class="h-5 w-5" />
                    </a>
                    <form method="POST" action="{{ route('preferences.motion') }}">
                        @csrf
                        <button type="submit" class="rounded-lg p-2 transition hover:bg-white/5 hover:text-white {{ auth()->user()->motion_enabled === false ? 'text-slate-600' : 'text-slate-400' }}"
                                title="{{ auth()->user()->motion_enabled === false ? 'Animaties staan uit, klik om ze aan te zetten' : 'Animaties staan aan, klik om ze uit te zetten' }}">
                            <x-icon name="bolt" class="h-5 w-5" />
                        </button>
                    </form>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-lg p-2 text-slate-400 transition hover:bg-white/5 hover:text-white" title="Uitloggen">
                            <x-icon name="arrow-right-on-rectangle" class="h-5 w-5" />
                        </button>
                    </form>
                </div>
            </div>
            <a href="{{ route('privacy') }}" class="mt-2 inline-block text-xs text-slate-500 transition hover:text-white">Privacyverklaring</a>
        </div>
    </aside>

    {{-- Mobiele header --}}
    <header class="sticky top-0 z-30 flex h-14 items-center justify-between bg-surface-950 px-4 text-white lg:hidden">
        <a href="{{ route('dashboard') }}">
            <img src="{{ asset('images/renovion-logo.svg') }}" alt="Renovion" class="h-8 w-auto">
        </a>
        @if ($title)
            <span class="absolute left-1/2 -translate-x-1/2 text-sm font-semibold">{{ $title }}</span>
        @endif
        <div class="flex items-center gap-1">
            <a href="{{ route('onboarding') }}" class="rounded-lg p-2 {{ request()->routeIs('onboarding') ? 'text-brand-400' : 'text-slate-400' }}" title="Aan de slag: zo werkt het systeem">
                <x-icon name="sparkles" class="h-5 w-5" />
            </a>
            @if ($canCrm)
                <a href="{{ route('search') }}" class="rounded-lg p-2 text-slate-400" title="Zoeken">
                    <x-icon name="magnifying-glass" class="h-5 w-5" />
                </a>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-lg p-2 text-slate-400" title="Uitloggen">
                    <x-icon name="arrow-right-on-rectangle" class="h-5 w-5" />
                </button>
            </form>
        </div>
    </header>
    @else
        @include('partials.app.topbar')
    @endunless

    <div @class(['lg:pl-64' => ! $isApp])>
        @unless ($isApp)
        {{-- Desktop topbar: zoeken + snel aanmaken --}}
        <header class="sticky top-0 z-30 hidden h-16 items-center gap-4 bg-surface-900/90 px-8 backdrop-blur lg:flex">
            @if ($canCrm)
                <form action="{{ route('search') }}" method="GET" class="relative w-full max-w-md">
                    <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-slate-500" />
                    <input type="search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}"
                           placeholder="Zoek klanten, projecten, taken, medewerkers…"
                           class="w-full rounded-full border-surface-700 bg-surface-850 py-2.5 pl-10 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
                </form>

                <div class="ml-auto flex items-center gap-2">
                    <a href="{{ route('onboarding') }}" title="Aan de slag: zo werkt het systeem"
                       class="flex h-9 w-9 items-center justify-center rounded-full border border-surface-700 text-slate-400 transition hover:border-surface-600 hover:text-white">
                        <x-icon name="sparkles" class="h-4 w-4" />
                    </a>
                    <a href="{{ route('attention.index') }}" title="Inbox: wat aandacht vraagt"
                       class="relative flex h-9 w-9 items-center justify-center rounded-full border border-surface-700 text-slate-400 transition hover:border-surface-600 hover:text-white">
                        <x-icon name="bell" class="h-4 w-4" />
                        @if ($aandachtCount > 0)
                            <span class="absolute -top-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-brand-500 ring-2 ring-surface-900"></span>
                        @endif
                    </a>

                    <div x-data="{ open: false }" class="relative ml-1">
                        <button @click="open = !open" @click.outside="open = false"
                                class="flex items-center gap-1.5 rounded-full bg-brand-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
                            <x-icon name="plus" class="h-4 w-4" />
                            Nieuw
                        </button>
                        <div x-show="open" x-cloak x-transition.origin.top.right
                             class="motion-pop absolute right-0 z-40 mt-2 w-52 overflow-hidden rounded-2xl border border-surface-700 bg-surface-800 py-1 shadow-2xl shadow-black/40">
                            <a href="{{ route('leads.create') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-medium text-slate-200 hover:bg-white/5"><x-icon name="inbox" class="h-4 w-4 text-slate-500" /> Nieuwe aanvraag</a>
                            <a href="{{ route('projects.create') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-medium text-slate-200 hover:bg-white/5"><x-icon name="building-office" class="h-4 w-4 text-slate-500" /> Nieuw project</a>
                            <a href="{{ route('tasks.index') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-medium text-slate-200 hover:bg-white/5"><x-icon name="clipboard-check" class="h-4 w-4 text-slate-500" /> Nieuwe taak</a>
                        </div>
                    </div>
                </div>
            @endif
        </header>

        @endunless

        {{-- Tabbladen binnen de sectie: de sidebar wijst de sectie aan, hier kies je het scherm --}}
        @if (! $isApp && count($sectionTabs) > 1)
            <nav class="scrollbar-none -mb-px flex gap-1 overflow-x-auto border-b border-surface-700 px-4 lg:px-8" aria-label="Tabbladen">
                @foreach ($sectionTabs as $tab)
                    <a href="{{ $tab['url'] }}" @class([
                            'shrink-0 border-b-2 px-3 py-3 text-sm font-semibold whitespace-nowrap transition',
                            'border-brand-500 text-white' => $tab['active'],
                            'border-transparent text-slate-400 hover:text-slate-200' => ! $tab['active'],
                        ]) @if ($tab['active']) aria-current="page" @endif>{{ $tab['label'] }}</a>
                @endforeach
            </nav>
        @endif

        {{-- Content --}}
        <main class="px-4 pt-4 pb-[calc(7rem_+_env(safe-area-inset-bottom))] lg:px-8 lg:pt-6 lg:pb-12">
            @if ($isApp)
                @include('partials.app.section-tabs')
            @endif

            {{-- Flash messages --}}
            @if (session('success') || session('error'))
                <div data-flash x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-cloak
                     class="mb-4 flex items-center justify-between rounded-xl px-4 py-3 text-sm font-medium {{ session('error') ? 'bg-red-500/15 text-red-300' : 'bg-green-500/15 text-green-300' }}">
                    <span>{{ session('error') ?? session('success') }}</span>
                    <button @click="show = false" class="ml-3 opacity-60 hover:opacity-100" title="Sluiten"><x-icon name="x-mark" class="h-3.5 w-3.5" /></button>
                </div>
            @endif

            {{ $slot }}

            @if ($isApp)
                @include('partials.motion-stage')
            @endif
        </main>
    </div>

    {{-- Nova AI-assistent --}}
    @can('manage-crm')
    <div x-data="nova('{{ route('nova.propose') }}', '{{ route('nova.execute') }}')" @nova-open.window="openPanel()"
         @renovion-back.window="if (open) { open = false; $event.preventDefault(); }">
        @if ($isApp)
            <button type="button" @click="openPanel()" class="app-nova hide-on-keyboard" title="Nova" aria-label="Nova openen">
                <x-nova-avatar class="h-full w-full" />
                @if ($aandachtCount > 0)
                    <span class="cnt">{{ $aandachtCount }}</span>
                @endif
                <span class="spark"><x-icon name="sparkles" class="h-3 w-3" /></span>
            </button>
        @else
        <button @click="openPanel()"
                class="fixed right-4 bottom-24 z-40 rounded-full shadow-lg shadow-navy-950/30 transition hover:scale-105 active:scale-95 lg:bottom-8"
                title="Nova">
            <x-nova-avatar class="h-14 w-14 ring-2 ring-brand-500" />
            <span class="absolute -right-0.5 -bottom-0.5 flex h-6 w-6 items-center justify-center rounded-full bg-brand-500 text-white ring-2 ring-white">
                <x-icon name="microphone" class="h-3.5 w-3.5" />
            </span>
        </button>
        @endif

        <div data-popup x-show="open" x-cloak @click.self="open = false" @keydown.escape.window="open = false"
             class="fixed inset-0 z-50 flex items-end justify-center bg-navy-950/60 backdrop-blur-sm lg:items-center">
            <div x-swipe-dismiss="open = false" class="flex h-[85dvh] w-full max-w-lg flex-col rounded-t-2xl bg-surface-800 lg:h-[70vh] lg:rounded-2xl" @click.stop>
                <x-sheet-handle class="mt-2 lg:hidden" />

                {{-- Header --}}
                <div class="flex items-center gap-3 border-b border-surface-700 p-4">
                    <x-nova-avatar class="ring-2 ring-brand-500" />
                    <div class="flex-1">
                        <p class="font-bold text-white">Nova</p>
                        <p class="text-xs text-slate-400">Stelt voor - jij bevestigt</p>
                    </div>
                    <button @click="open = false" class="rounded-lg p-2 text-slate-500 transition hover:bg-surface-750 hover:text-slate-300" title="Sluiten">
                        <x-icon name="x-mark" class="h-5 w-5" />
                    </button>
                </div>

                {{-- Berichten --}}
                <div x-ref="novaMessages" class="flex-1 space-y-3 overflow-y-auto p-4">
                    <template x-for="(message, index) in messages" :key="index">
                        <div class="flex gap-2" :class="message.role === 'user' ? 'justify-end' : ''">
                            <template x-if="message.role === 'nova'">
                                <img src="{{ asset('nova.jpg') }}" alt="Nova" class="mt-0.5 h-8 w-8 shrink-0 rounded-full object-cover">
                            </template>

                            <div class="max-w-[85%]">
                                <div :class="message.role === 'user' ? 'bg-navy-950 text-white' : 'bg-surface-750 text-white'"
                                     class="rounded-2xl px-4 py-2.5 text-sm whitespace-pre-line">
                                    <span x-text="message.text"></span>
                                    <template x-if="message.url">
                                        <a :href="message.url" class="mt-1 block text-xs font-semibold text-brand-400 hover:text-brand-500">Bekijken →</a>
                                    </template>
                                </div>

                                {{-- Actievoorstel met bevestiging --}}
                                <template x-if="message.proposal">
                                    <div class="mt-2 rounded-2xl border border-brand-500/30 bg-brand-500/10 p-3">
                                        <p class="text-sm font-medium text-white" x-text="message.proposal.preview"></p>
                                        <div class="mt-3 flex gap-2" x-show="message.proposal.state === 'open' || message.proposal.state === 'busy'">
                                            <button @click="confirm(message)" :disabled="message.proposal.state === 'busy'"
                                                    class="flex-1 rounded-lg bg-brand-500 py-2 text-xs font-bold text-white transition hover:bg-brand-600 disabled:opacity-50">
                                                <span x-text="message.proposal.state === 'busy' ? 'Bezig…' : 'Bevestigen'"></span>
                                            </button>
                                            <button @click="cancel(message)" :disabled="message.proposal.state === 'busy'"
                                                    class="rounded-lg border border-surface-600 bg-surface-800 px-4 py-2 text-xs font-semibold text-slate-300 transition hover:bg-surface-850 disabled:opacity-50">
                                                Annuleren
                                            </button>
                                        </div>
                                        <p x-show="message.proposal.state === 'done'" class="mt-2 flex items-center gap-1 text-xs font-semibold text-green-300"><x-icon name="check" class="h-3.5 w-3.5" /> Uitgevoerd</p>
                                        <p x-show="message.proposal.state === 'cancelled'" class="mt-2 text-xs font-semibold text-slate-500">Geannuleerd</p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div x-show="busy" class="flex items-center gap-2">
                        <img src="{{ asset('nova.jpg') }}" alt="Nova" class="h-8 w-8 shrink-0 rounded-full object-cover">
                        <span class="flex items-center gap-2 rounded-2xl bg-surface-750 px-4 py-2.5 text-xs text-slate-400">
                            <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-brand-500"></span>
                            Nova denkt na…
                        </span>
                    </div>
                </div>

                {{-- Invoer --}}
                <form @submit.prevent="send()" class="flex items-center gap-2 border-t border-surface-700 p-3 pb-[calc(0.75rem_+_env(safe-area-inset-bottom))]">
                    <button type="button" @click="toggleMic()" x-show="speechSupported"
                            :class="listening ? 'bg-red-500 text-white animate-pulse' : 'bg-surface-750 text-slate-400 hover:bg-surface-700'"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full transition"
                            :title="listening ? 'Stop met luisteren' : 'Spreek je opdracht in'">
                        <x-icon name="microphone" class="h-5 w-5" />
                    </button>
                    <input x-ref="novaInput" x-model="input" type="text" :disabled="busy"
                           placeholder="Typ of spreek je opdracht…" aria-label="Opdracht voor Nova"
                           class="flex-1 rounded-xl border-surface-600 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <button type="submit" :disabled="busy || !input.trim()"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-500 text-white transition hover:bg-brand-600 disabled:opacity-40" title="Versturen">
                        <x-icon name="paper-airplane" class="h-4 w-4" />
                    </button>
                </form>
            </div>
        </div>
    </div>
    @endcan

    {{-- Mobiele bottom-nav: per rol de vier of vijf schermen waar je echt heen gaat --}}
    @if ($isApp)
        @include('partials.app.shell')
    @else
    @php($tabs = \App\Support\Navigation::bottomNavFor(auth()->user()))
    @if ($tabs !== [])
        <nav class="fixed inset-x-0 bottom-0 z-40 grid border-t border-surface-700 bg-surface-800 pb-[env(safe-area-inset-bottom)] lg:hidden" style="grid-template-columns: repeat({{ count($tabs) }}, minmax(0, 1fr))">
            @foreach ($tabs as $tab)
                <a href="{{ $tab['url'] }}" class="flex flex-col items-center gap-0.5 py-2 {{ $tab['active'] ? 'text-brand-400' : 'text-slate-500' }}">
                    <x-icon :name="$tab['icon']" class="h-6 w-6" />
                    <span class="text-[10px] font-semibold">{{ $tab['label'] }}</span>
                </a>
            @endforeach
        </nav>
    @endif
    @endif

    <x-photo-viewer />

</body>
</html>
