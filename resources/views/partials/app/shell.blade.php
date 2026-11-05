{{-- Onderbalk, zijmenu en hulp van de iOS/Android-app (prototype Renovion OS). Open/dicht via $store.shell (app.js). --}}
@php
    $section = \App\Support\AppSection::current()['key'] ?? null;
    // Geopend vanuit de schermen in de app (AppWebSessionController): die schermen gaan terug naar de app.
    $bundel = session('app_bundle');
    // Menu op één plek (AppMenu), gelijk aan de schermen in de app.
    $menuItem = fn (array $item) => [
        ...$item,
        'href' => isset($item['route']) ? \App\Support\AppMenu::href($item['route'], $bundel) : null,
        'active' => isset($item['section']) && $section === $item['section'],
        'count' => match ($item['count'] ?? null) {
            'attention' => $aandachtCount,
            'notifications' => auth()->user()->unreadNotifications()->count(),
            default => 0,
        },
        'chat' => ($item['count'] ?? null) === 'chat',
    ];
    $tabs = array_map($menuItem, \App\Support\AppMenu::tabs(auth()->user()));
    $groepen = array_map(fn (array $items) => array_map($menuItem, $items), \App\Support\AppMenu::groups(auth()->user()));
@endphp

{{-- Ongelezen chatberichten live bijhouden, zoals op de website: bij een push terwijl de app open is,
     bij terugkeren naar de app, en elke 30 seconden zolang het scherm zichtbaar is. --}}
<nav x-data="{
         async refresh() {
             try {
                 const response = await fetch(@js(route('chat.unread')), { headers: { Accept: 'application/json' } });
                 if (response.ok) $store.shell.unread = (await response.json()).count;
             } catch { /* volgende keer opnieuw */ }
         },
         timer: null,
         onVisible: null,
         init() {
             $store.shell.unread = {{ $chatUnread }};
             this.timer = setInterval(() => document.visibilityState === 'visible' && this.refresh(), 30000);
             this.onVisible = () => document.visibilityState === 'visible' && this.refresh();
             document.addEventListener('visibilitychange', this.onVisible);
         },
         {{-- Snel wisselen (Turbo) vervangt de onderbalk zonder de pagina te herladen: zonder opruimen stapelen de timers op. --}}
         destroy() {
             clearInterval(this.timer);
             document.removeEventListener('visibilitychange', this.onVisible);
         },
     }"
     @renovion-push.window="refresh()"
     class="app-bnav phone-tabbar hide-on-keyboard fixed inset-x-0 bottom-0 z-40 grid grid-cols-5 bg-white pb-safe">
    @foreach ($tabs as $tab)
        <a href="{{ $tab['href'] }}" @class(['on' => $tab['active']]) {{ ($tab['chat'] ?? false) ? '' : 'data-turbo-preload' }}>
            <span class="relative">
                <x-icon :name="$tab['icon']" class="h-[22px] w-[22px]" />
                @if (($tab['count'] ?? 0) > 0)
                    <span class="cnt">{{ $tab['count'] > 99 ? '99+' : $tab['count'] }}</span>
                @elseif ($tab['chat'] ?? false)
                    <span class="cnt" x-show="$store.shell.unread > 0" x-cloak x-text="$store.shell.unread > 9 ? '9+' : $store.shell.unread"></span>
                @endif
            </span>
            <span class="tab-label">{{ $tab['label'] }}</span>
        </a>
    @endforeach

    <button type="button" @click="$store.shell.drawer = true">
        <span class="relative">
            <x-icon name="list-bullet" class="h-[22px] w-[22px]" />
            @if ($canCrm)
                <span class="absolute -top-0.5 -right-1 h-2 w-2 rounded-full bg-brand-500 ring-2 ring-white" x-show="$store.shell.unread > 0" x-cloak></span>
            @endif
        </span>
        <span class="tab-label">Menu</span>
    </button>
</nav>

{{-- Zijmenu --}}
<div x-data x-show="$store.shell.drawer" x-cloak class="app-drawer fixed inset-0 z-[60]"
     x-effect="$store.shell.drawer; setTimeout(() => window.dispatchEvent(new Event('renovion-top-changed')), 230)"
     @keydown.escape.window="$store.shell.drawer = false">
    <div class="app-scrim absolute inset-0" x-show="$store.shell.drawer" x-transition.opacity @click="$store.shell.drawer = false"></div>

    <aside class="absolute inset-y-0 left-0 flex w-[264px] flex-col bg-navy-950 pt-safe"
           x-show="$store.shell.drawer"
           x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full">
        <a href="{{ route('dashboard') }}" class="block px-4 pt-5 pb-3">
            <x-os-logo class="h-[58px] w-auto" />
        </a>

        <nav class="sidebar-scroll flex-1 overflow-y-auto pb-3 pl-3 pr-0.5">
            @foreach ($groepen as $groep => $items)
                <div x-data="{ open: true }" class="mb-2">
                    <button type="button" @click="open = !open" :aria-expanded="open" class="app-ngroup">
                        {{ $groep }}
                        <x-icon name="chevron-down" class="h-3.5 w-3.5 transition" ::class="!open && '-rotate-90'" />
                    </button>
                    <div x-show="open" class="space-y-0.5">
                        @foreach ($items as $item)
                            @if ($item['nova'] ?? false)
                                <button type="button" @click="$store.shell.drawer = false; $dispatch('nova-open')" class="app-nitem">
                                    <x-icon :name="$item['icon']" class="h-5 w-5" />
                                    <span class="flex-1 truncate">{{ $item['label'] }}</span>
                                </button>
                            @else
                                <a href="{{ $item['href'] }}" @class(['app-nitem', 'active' => $item['active']])>
                                    <x-icon :name="$item['icon']" class="h-5 w-5" />
                                    <span class="flex-1 truncate">{{ $item['label'] }}</span>
                                    @if (($item['count'] ?? 0) > 0)
                                        <span @class(['cnt', 'accent' => $item['accent'] ?? false])>{{ $item['count'] }}</span>
                                    @elseif ($item['chat'] ?? false)
                                        <span class="cnt" x-show="$store.shell.unread > 0" x-cloak x-text="$store.shell.unread"></span>
                                    @endif
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        <div class="flex items-center gap-2.5 border-t border-navy-800 p-3 pb-[calc(0.75rem_+_env(safe-area-inset-bottom))]">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-navy-700 text-xs font-bold text-navy-100">
                {{ str(auth()->user()->name)->substr(0, 1)->upper() }}
            </span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-semibold text-white">{{ auth()->user()->name }}</span>
                <span class="block truncate text-xs text-navy-300">{{ auth()->user()->role->label() }}</span>
            </span>
            <a href="{{ route('notification-settings.edit') }}" class="tap p-2 text-navy-300" title="Instellingen">
                <x-icon name="cog" class="h-5 w-5" />
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="tap p-2 text-navy-300" title="Uitloggen">
                    <x-icon name="arrow-right-on-rectangle" class="h-5 w-5" />
                </button>
            </form>
        </div>
    </aside>
</div>

{{-- Hulp (het prototype heeft hier de Academy; die bestaat in de app nog niet) --}}
<div x-data data-popup x-show="$store.shell.help" x-cloak @click.self="$store.shell.help = false"
     class="fixed inset-0 z-[60] flex items-end bg-navy-950/60 backdrop-blur-sm">
    <div x-swipe-dismiss="$store.shell.help = false" class="max-h-[85dvh] w-full overflow-y-auto rounded-t-[20px] bg-white px-4 pt-3 pb-[calc(1rem_+_env(safe-area-inset-bottom))]">
        <x-sheet-handle class="mb-3" />
        <h2 class="mb-2 text-lg font-bold text-navy-950">Hulp</h2>
        <div class="app-card-b !p-0">
            @if ($canCrm)
                <button type="button" @click="$store.shell.help = false; $dispatch('nova-open')" class="app-li w-full text-left">
                    <span class="app-ic or"><x-icon name="sparkles" class="h-[18px] w-[18px]" /></span>
                    <span class="min-w-0 flex-1"><b>Vraag het Nova</b><small>Inspreken of typen, Nova zet het voor je klaar</small></span>
                </button>
            @endif
            <a href="{{ route('notification-settings.edit') }}" class="app-li">
                <span class="app-ic blue"><x-icon name="bell" class="h-[18px] w-[18px]" /></span>
                <span class="min-w-0 flex-1"><b>Meldingen instellen</b><small>Kies welke pushmeldingen je wilt krijgen</small></span>
            </a>
            <div x-data="{ open: false }" class="app-li cursor-pointer" @click="open = !open">
                <span class="app-ic ok"><x-icon name="sparkles" class="h-[18px] w-[18px]" /></span>
                <span class="min-w-0 flex-1">
                    <b>Wat is nieuw</b>
                    <small x-show="!open">De laatste verbeteringen in de app</small>
                    <ul x-show="open" x-cloak class="mt-1 list-disc space-y-0.5 pl-4 text-[12.5px] text-gray-600">
                        @foreach (\App\Support\AppNews::ITEMS as $nieuws)
                            <li>{{ $nieuws }}</li>
                        @endforeach
                    </ul>
                </span>
                <x-icon name="chevron-down" class="mt-2.5 h-4 w-4 text-gray-300 transition" ::class="open && 'rotate-180'" />
            </div>
            <a href="mailto:info@renovion.nl" class="app-li">
                <span class="app-ic vio"><x-icon name="envelope" class="h-[18px] w-[18px]" /></span>
                <span class="min-w-0 flex-1"><b>Mail Renovion</b><small>info@renovion.nl</small></span>
            </a>
        </div>
    </div>
</div>
