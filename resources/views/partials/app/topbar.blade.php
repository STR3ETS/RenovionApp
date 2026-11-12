{{-- Topbalk van de iOS/Android-app (prototype Renovion OS): menu, zoeken, hulp, meldingen en "+". --}}
@php
    $nieuweMeldingen = auth()->user()->unreadNotifications()->exists();
    $plusMenu = array_map(fn (array $item) => [...$item, 'href' => \App\Support\AppMenu::href($item['route'], session('app_bundle'))], \App\Support\AppMenu::plus(auth()->user()));
@endphp

<header x-data="{ plus: false }" @renovion-back.window="if (plus) { plus = false; $event.preventDefault(); }"
        class="app-top phone-header sticky top-0 z-30 box-content flex h-[59px] items-center gap-2 bg-white px-3 pt-safe">
    <button type="button" @click="$store.shell.drawer = true" class="app-iconbtn tap" title="Menu" aria-label="Menu openen">
        <x-icon name="bars-3" class="h-[18px] w-[18px]" />
    </button>

    @if ($canCrm)
        <a href="{{ \App\Support\AppMenu::href('search', session('app_bundle')) }}" class="flex h-9 min-w-0 flex-1 items-center gap-2 rounded-full border border-gray-200 bg-white px-3 text-sm text-gray-500">
            <x-icon name="magnifying-glass" class="h-4 w-4" />
            <span class="truncate">Zoeken</span>
        </a>
    @else
        <a href="{{ route('dashboard') }}" class="min-w-0 flex-1">
            <x-os-logo dark class="h-10 w-auto" />
        </a>
    @endif

    <button type="button" @click="$store.shell.help = true" class="app-iconbtn tap" title="Hulp" aria-label="Hulp openen">
        <x-icon name="question-mark-circle" class="h-[18px] w-[18px]" />
    </button>

    <a href="{{ \App\Support\AppMenu::href('notifications.index', session('app_bundle')) }}" class="app-iconbtn tap relative" title="Meldingen" aria-label="Meldingen">
        <x-icon name="bell" class="h-[18px] w-[18px]" />
        @if ($nieuweMeldingen)
            <span class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-brand-500 ring-2 ring-white"></span>
        @endif
    </a>

    <div class="relative">
        <button type="button" @click="plus = !plus" @click.outside="plus = false"
                class="tap flex h-[34px] w-[42px] items-center justify-center rounded-full bg-brand-500 text-white active:bg-brand-600" title="Nieuw" aria-label="Nieuw">
            <x-icon name="plus" class="h-[18px] w-[18px]" />
        </button>

        <div x-show="plus" x-cloak x-transition.origin.top.right class="app-menu">
            @foreach ($plusMenu as $actie)
                <a href="{{ $actie['href'] }}"><x-icon :name="$actie['icon']" class="h-4 w-4" /> {{ $actie['label'] }}</a>
            @endforeach
            @if ($canCrm)
                <hr>
                <button type="button" @click="plus = false; $dispatch('nova-open')"><x-icon name="sparkles" class="h-4 w-4" /> Vraag Nova</button>
            @endif
        </div>
    </div>
</header>
