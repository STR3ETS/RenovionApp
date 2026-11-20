@props(['title' => null])

@php
    // Het thema: licht, donker of "auto" (het besturingssysteem volgen). Bij auto
    // zet het scriptje in de head het goede thema voordat er iets getekend wordt.
    $themePref = auth()->user()?->theme ?? 'auto';
@endphp

<!DOCTYPE html>
<html lang="nl" data-theme="{{ $themePref === 'dark' ? 'dark' : 'light' }}" data-theme-pref="{{ $themePref }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0D1A2E">
    <title>{{ $title ? $title.' - ' : '' }}{{ config('app.name') }}</title>

    <script>
        (function () {
            var d = document.documentElement;
            var q = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
            function zet() { if (d.dataset.themePref === 'auto') { d.dataset.theme = q && q.matches ? 'dark' : 'light'; } }
            zet();
            if (q && q.addEventListener) { q.addEventListener('change', zet); }
        })();
    </script>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700|plus-jakarta-sans:400,500,600,700" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Chrome laadt pagina's alvast bij hover: navigatie voelt direct (stijlgids §08).
         Chat (polling) en de offerte-builder (autosave) worden niet vooraf geladen. --}}
    <script type="speculationrules">
    {
        "prerender": [{
            "where": { "and": [
                { "href_matches": "/*" },
                { "not": { "href_matches": "/chat*" } },
                { "not": { "href_matches": "/offertes/*/builder*" } },
                { "not": { "href_matches": "/offertetool/*" } }
            ]},
            "eagerness": "moderate"
        }]
    }
    </script>
</head>
@php
    $user = auth()->user();
    $canCrm = $user->can('manage-crm');
    $aandachtCount = $canCrm ? \App\Services\AttentionService::cachedCount() : 0;
    $chatUnread = \App\Models\ChatChannel::unreadTotalFor($user);

    // Het menu komt uit één bron: App\Support\Navigation (secties, tabbladen, rechten).
    $sections = \App\Support\Navigation::forUser($user);
    $sectionTabs = \App\Support\Navigation::tabsFor($user);
    $currentSection = \App\Support\Navigation::currentSection($user);
    $counters = ['inbox' => $aandachtCount, 'chat' => $chatUnread];
    $motionOn = $user->motion_enabled !== false;
@endphp
<body class="app-shell min-h-screen bg-surface-900 font-sans text-ink antialiased"
      data-sec="{{ $currentSection['id'] ?? 'vandaag' }}"
      data-motion="{{ $motionOn ? 'on' : 'off' }}">

    {{-- Zijbalk (desktop): navy in beide thema's --}}
    <aside class="side on-navy fixed inset-y-0 left-0 z-40 hidden w-60 lg:flex"
           x-data="{ dicht: {}, init() { try { this.dicht = JSON.parse(localStorage.getItem('menu-dicht') || '{}'); } catch (e) {} }, wissel(g) { this.dicht[g] = ! this.dicht[g]; try { localStorage.setItem('menu-dicht', JSON.stringify(this.dicht)); } catch (e) {} } }">
        <a href="{{ route('dashboard') }}" class="logo">
            <x-logo />
        </a>

        <nav class="nav" aria-label="Hoofdmenu">
            @foreach (\App\Support\Navigation::GROUPS as $group)
                @continue(empty($sections[$group]))
                <div class="ngroup" :class="dicht[@js($group)] && 'closed'">
                    <button type="button" @click="wissel(@js($group))" :aria-expanded="dicht[@js($group)] ? 'false' : 'true'">
                        {{ $group }}
                        <x-icon name="chevron-down" />
                    </button>
                    <div class="nitems">
                        @foreach ($sections[$group] as $item)
                            @php($badge = $item['counter'] ? ($counters[$item['counter']] ?? 0) : 0)
                            <a href="{{ $item['url'] }}" class="nitem {{ $item['active'] ? 'active' : '' }}" @if ($item['active']) aria-current="page" @endif>
                                <x-icon :name="$item['icon']" />
                                <span>{{ $item['label'] }}</span>
                                @if ($badge > 0)
                                    <span class="cnt {{ $item['counter'] === 'inbox' ? '' : 'quiet' }}">{{ $badge }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        <div class="side-user" x-data="{ open: false }" @keydown.escape.window="open = false">
            <span class="av">{{ str($user->name)->substr(0, 1)->upper() }}</span>
            <div class="min-w-0 flex-1">
                <div class="nm truncate">{{ $user->name }}</div>
                <div class="rl truncate">{{ $user->role->label() }}</div>
            </div>
            <div class="relative">
                <button type="button" class="sbtn" @click="open = ! open" @click.outside="open = false" aria-label="Voorkeuren en uitloggen" title="Voorkeuren en uitloggen">
                    <x-icon name="cog-6-tooth" />
                </button>

                {{-- Voorkeuren: thema, animaties, uitleg en uitloggen. Dit venster volgt het thema van de pagina. --}}
                <div x-show="open" x-cloak x-transition.origin.bottom.left
                     class="pop motion-pop absolute bottom-full left-0 z-50 mb-2 w-64 p-3 text-ink" data-user-menu>
                    <p class="lbl mb-2">Thema</p>
                    <form method="POST" action="{{ route('preferences.theme') }}" class="tabs w-full">
                        @csrf
                        @foreach (['auto' => ['Auto', 'computer-desktop'], 'light' => ['Licht', 'sun'], 'dark' => ['Donker', 'moon']] as $value => [$label, $icon])
                            <button type="submit" name="theme" value="{{ $value }}" class="tab flex-1 justify-center px-2 {{ $themePref === $value ? 'on' : '' }}"
                                    @if ($themePref === $value) aria-pressed="true" @endif>
                                <x-icon :name="$icon" class="h-3.5 w-3.5" />{{ $label }}
                            </button>
                        @endforeach
                    </form>

                    <div class="mt-3 grid gap-1 border-t border-line2 pt-2">
                        <form method="POST" action="{{ route('preferences.motion') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-2 py-2 text-left text-[13px] font-medium text-ink2 hover:bg-surface-850 hover:text-ink">
                                <x-icon name="bolt" class="h-4 w-4 text-muted" />
                                {{ $motionOn ? 'Animaties uitzetten' : 'Animaties aanzetten' }}
                            </button>
                        </form>
                        <a href="{{ route('onboarding') }}" class="flex items-center gap-2.5 rounded-lg px-2 py-2 text-[13px] font-medium text-ink2 hover:bg-surface-850 hover:text-ink">
                            <x-icon name="sparkles" class="h-4 w-4 text-muted" />
                            Aan de slag
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-2 py-2 text-left text-[13px] font-medium text-ink2 hover:bg-surface-850 hover:text-ink">
                                <x-icon name="arrow-right-on-rectangle" class="h-4 w-4 text-muted" />
                                Uitloggen
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    {{-- Mobiele kop: navy met het woordmerk --}}
    <header class="on-navy sticky top-0 z-30 flex h-14 items-center justify-between bg-surface-950 px-4 lg:hidden">
        <a href="{{ route('dashboard') }}" aria-label="Naar Vandaag">
            <x-logo :tagline="false" class="h-8 w-auto" />
        </a>
        <div class="flex items-center gap-1">
            <a href="{{ route('onboarding') }}" class="rounded-lg p-2 {{ request()->routeIs('onboarding') ? 'text-brand-400' : 'text-muted' }}" title="Aan de slag: zo werkt het systeem">
                <x-icon name="sparkles" class="h-5 w-5" />
            </a>
            @if ($canCrm)
                <a href="{{ route('search') }}" class="rounded-lg p-2 text-muted" title="Zoeken">
                    <x-icon name="magnifying-glass" class="h-5 w-5" />
                </a>
            @endif
            <form method="POST" action="{{ route('preferences.theme') }}">
                @csrf
                <button type="submit" name="theme" value="{{ ['auto' => 'light', 'light' => 'dark', 'dark' => 'auto'][$themePref] ?? 'auto' }}" class="rounded-lg p-2 text-muted"
                        title="Thema: {{ ['auto' => 'automatisch', 'light' => 'licht', 'dark' => 'donker'][$themePref] ?? 'automatisch' }}. Tik om te wisselen.">
                    <x-icon :name="['auto' => 'computer-desktop', 'light' => 'sun', 'dark' => 'moon'][$themePref] ?? 'computer-desktop'" class="h-5 w-5" />
                </button>
            </form>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-lg p-2 text-muted" title="Uitloggen">
                    <x-icon name="arrow-right-on-rectangle" class="h-5 w-5" />
                </button>
            </form>
        </div>
    </header>

    <div class="lg:pl-60">
        {{-- Bovenbalk (desktop): zoeken, uitleg, inbox en snel aanmaken --}}
        <header class="top sticky top-0 z-30 hidden lg:flex">
            @if ($canCrm)
                <form action="{{ route('search') }}" method="GET" class="search" role="search">
                    <x-icon name="magnifying-glass" />
                    <input type="search" name="q" id="globaal-zoeken" value="{{ request()->routeIs('search') ? request('q') : '' }}"
                           placeholder="Zoek klanten, projecten, taken, medewerkers" aria-label="Zoeken" autocomplete="off">
                </form>
            @endif

            <div class="flex-1"></div>

            <span class="hidden text-[11.5px] whitespace-nowrap text-muted xl:inline">{{ str(now()->translatedFormat('l j F Y'))->ucfirst() }}</span>

            <a href="{{ route('onboarding') }}" class="iconbtn" title="Aan de slag: zo werkt het systeem" aria-label="Aan de slag">
                <x-icon name="question-mark-circle" />
            </a>

            @if ($canCrm)
                <a href="{{ route('attention.index') }}" class="iconbtn" title="Inbox: wat aandacht vraagt" aria-label="Inbox">
                    <x-icon name="bell" />
                    @if ($aandachtCount > 0)
                        <span class="dot"></span>
                    @endif
                </a>

                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" @click.outside="open = false" @keydown.escape.window="open = false" data-sneltoets-nieuw title="Nieuw (N)" class="btn pri">
                        <x-icon name="plus" />
                        <span>Nieuw</span>
                    </button>
                    <div x-show="open" x-cloak x-transition.origin.top.right class="pop motion-pop absolute right-0 z-40 mt-2 w-52 overflow-hidden py-1">
                        <a href="{{ route('leads.create') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-[13px] font-medium text-ink hover:bg-surface-850"><x-icon name="inbox" class="h-4 w-4 text-muted" /> Nieuwe aanvraag</a>
                        <a href="{{ route('projects.create') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-[13px] font-medium text-ink hover:bg-surface-850"><x-icon name="building-office" class="h-4 w-4 text-muted" /> Nieuw project</a>
                        <a href="{{ route('tasks.index') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-[13px] font-medium text-ink hover:bg-surface-850"><x-icon name="clipboard-check" class="h-4 w-4 text-muted" /> Nieuwe taak</a>
                    </div>
                </div>
            @endif
        </header>

        {{-- Content --}}
        <main class="page">
            {{-- Tabbladen binnen de sectie: de zijbalk wijst de sectie aan, hier kies je het scherm --}}
            @if (count($sectionTabs) > 1)
                <nav class="sectabs mb-[18px]" aria-label="{{ $currentSection['label'] ?? 'Tabbladen' }}">
                    @foreach ($sectionTabs as $tab)
                        <a href="{{ $tab['url'] }}" class="{{ $tab['active'] ? 'on' : '' }}" @if ($tab['active']) aria-current="page" @endif>{{ $tab['label'] }}</a>
                    @endforeach
                </nav>
            @endif

            {{-- Flash messages --}}
            @if (session('success') || session('error'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-cloak
                     class="mb-4 flex items-center justify-between rounded-xl border px-4 py-3 text-sm font-medium {{ session('error') ? 'border-bad/25 bg-bad-soft text-bad' : 'border-ok/25 bg-ok-soft text-ok' }}">
                    <span>{{ session('error') ?? session('success') }}</span>
                    <button @click="show = false" class="ml-3 opacity-60 hover:opacity-100" title="Sluiten"><x-icon name="x-mark" class="h-3.5 w-3.5" /></button>
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>

    {{-- Nova AI-assistent --}}
    @can('manage-crm')
    <div x-data="nova('{{ route('nova.propose') }}', '{{ route('nova.execute') }}')" @nova-ask.window="ask($event.detail)">
        <button @click="openPanel()"
                class="fixed right-4 bottom-24 z-40 rounded-full shadow-lg shadow-navy-950/30 transition hover:scale-105 active:scale-95 lg:bottom-8"
                title="Nova">
            <x-nova-avatar class="h-14 w-14 ring-2 ring-brand-500" />
            <span class="absolute -right-0.5 -bottom-0.5 flex h-6 w-6 items-center justify-center rounded-full bg-brand-500 text-white ring-2 ring-surface-900">
                <x-icon name="microphone" class="h-3.5 w-3.5" />
            </span>
        </button>

        <div x-show="open" x-cloak @click.self="open = false" @keydown.escape.window="open = false"
             class="fixed inset-0 z-50 flex items-end justify-center bg-navy-950/60 backdrop-blur-sm lg:items-center">
            <div class="flex h-[85dvh] w-full max-w-lg flex-col rounded-t-2xl bg-surface-800 lg:h-[70vh] lg:rounded-2xl" @click.stop>

                {{-- Header --}}
                <div class="flex items-center gap-3 border-b border-surface-700 p-4">
                    <x-nova-avatar class="ring-2 ring-brand-500" />
                    <div class="flex-1">
                        <p class="font-bold text-ink">Nova</p>
                        <p class="text-xs text-muted">Stelt voor - jij bevestigt</p>
                    </div>
                    <button @click="open = false" class="rounded-lg p-2 text-faint transition hover:bg-surface-750 hover:text-ink2" title="Sluiten">
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
                                <div :class="message.role === 'user' ? 'bg-navy-950 text-white' : 'bg-surface-750 text-ink'"
                                     class="rounded-2xl px-4 py-2.5 text-sm whitespace-pre-line">
                                    <span x-text="message.text"></span>
                                    <template x-if="message.url">
                                        <a :href="message.url" class="mt-1 block text-xs font-semibold text-brand-400 hover:text-brand-500">Bekijken →</a>
                                    </template>
                                </div>

                                {{-- Actievoorstel met bevestiging --}}
                                <template x-if="message.proposal">
                                    <div class="mt-2 rounded-2xl border border-brand-500/30 bg-brand-500/10 p-3">
                                        <p class="text-sm font-medium text-ink" x-text="message.proposal.preview"></p>
                                        <div class="mt-3 flex gap-2" x-show="message.proposal.state === 'open' || message.proposal.state === 'busy'">
                                            <button @click="confirm(message)" :disabled="message.proposal.state === 'busy'"
                                                    class="flex-1 rounded-lg bg-brand-500 py-2 text-xs font-bold text-white transition hover:bg-brand-600 disabled:opacity-50">
                                                <span x-text="message.proposal.state === 'busy' ? 'Bezig…' : 'Bevestigen'"></span>
                                            </button>
                                            <button @click="cancel(message)" :disabled="message.proposal.state === 'busy'"
                                                    class="btn sm">
                                                Annuleren
                                            </button>
                                        </div>
                                        <p x-show="message.proposal.state === 'done'" class="mt-2 flex items-center gap-1 text-xs font-semibold text-green-300"><x-icon name="check" class="h-3.5 w-3.5" /> Uitgevoerd</p>
                                        <p x-show="message.proposal.state === 'cancelled'" class="mt-2 text-xs font-semibold text-faint">Geannuleerd</p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div x-show="busy" class="flex items-center gap-2">
                        <img src="{{ asset('nova.jpg') }}" alt="Nova" class="h-8 w-8 shrink-0 rounded-full object-cover">
                        <span class="flex items-center gap-2 rounded-2xl bg-surface-750 px-4 py-2.5 text-xs text-muted">
                            <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-brand-500"></span>
                            Nova denkt na…
                        </span>
                    </div>
                </div>

                {{-- Invoer --}}
                <form @submit.prevent="send()" class="flex items-center gap-2 border-t border-surface-700 p-3">
                    <button type="button" @click="toggleMic()" x-show="speechSupported"
                            :class="listening ? 'bg-red-500 text-white animate-pulse' : 'bg-surface-750 text-muted hover:bg-surface-700'"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full transition"
                            :title="listening ? 'Stop met luisteren' : 'Spreek je opdracht in'">
                        <x-icon name="microphone" class="h-5 w-5" />
                    </button>
                    <input x-ref="novaInput" x-model="input" type="text" :disabled="busy"
                           placeholder="Typ of spreek je opdracht…"
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
    @php($tabs = \App\Support\Navigation::bottomNavFor(auth()->user()))
    @if ($tabs !== [])
        <nav class="fixed inset-x-0 bottom-0 z-40 grid border-t border-surface-700 bg-surface-800 pb-[env(safe-area-inset-bottom)] lg:hidden" style="grid-template-columns: repeat({{ count($tabs) }}, minmax(0, 1fr))">
            @foreach ($tabs as $tab)
                <a href="{{ $tab['url'] }}" class="flex flex-col items-center gap-0.5 py-2 {{ $tab['active'] ? 'text-brand-400' : 'text-faint' }}">
                    <x-icon :name="$tab['icon']" class="h-6 w-6" />
                    <span class="text-[10px] font-semibold">{{ $tab['label'] }}</span>
                </a>
            @endforeach
        </nav>
    @endif

    {{-- De voortgangsbalk bij een paginawissel, en wat de sneltoetsen nodig hebben --}}
    <div id="nav-voortgang" class="nav-voortgang" aria-hidden="true"></div>
    <script type="application/json" id="nav-schermen">@json(\App\Support\Navigation::shortcutsFor(auth()->user()))</script>

    <dialog id="sneltoetsen" class="motion-pop m-auto w-[min(92vw,26rem)] rounded-2xl border border-surface-700 bg-surface-800 p-5 text-ink shadow-2xl shadow-black/50 backdrop:bg-black/60 backdrop:backdrop-blur-sm" aria-label="Sneltoetsen">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 class="text-base font-bold text-ink">Snel<span class="text-brand-500">toetsen</span></h2>
            <form method="dialog">
                <button class="flex h-9 w-9 items-center justify-center rounded-full border border-surface-600 text-ink2 hover:text-ink" aria-label="Sluiten"><x-icon name="x-mark" class="h-4 w-4" /></button>
            </form>
        </div>
        <dl class="space-y-2 text-sm">
            @foreach ([
                'Ctrl K' => 'Zoeken in alles',
                'N' => 'Nieuw: aanvraag, project of taak',
                '?' => 'Deze lijst tonen of verbergen',
                'Esc' => 'Venster of menu sluiten',
            ] as $toets => $uitleg)
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-muted">{{ $uitleg }}</dt>
                    <dd><kbd class="rounded-md border border-surface-600 bg-surface-850 px-2 py-0.5 text-xs font-semibold text-ink">{{ $toets }}</kbd></dd>
                </div>
            @endforeach
            <div class="border-t border-surface-700 pt-2 text-xs text-faint">Eerst G, dan een letter, om naar een scherm te springen:</div>
            @foreach (\App\Support\Navigation::shortcutsFor(auth()->user())['help'] as $letter => $scherm)
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-muted">{{ $scherm }}</dt>
                    <dd><kbd class="rounded-md border border-surface-600 bg-surface-850 px-2 py-0.5 text-xs font-semibold text-ink">G {{ strtoupper($letter) }}</kbd></dd>
                </div>
            @endforeach
        </dl>
    </dialog>

</body>
</html>
