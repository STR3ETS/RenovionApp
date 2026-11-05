{{-- Onderstreepte tabs per sectie (prototype Renovion OS): schermen uit één sectie liggen naast elkaar. --}}
@php $sectionTabs = \App\Support\AppSection::tabs(); @endphp

@if ($sectionTabs !== [])
    <nav class="app-sectabs" aria-label="Onderdelen van deze sectie">
        @foreach ($sectionTabs as $tab)
            <a href="{{ \App\Support\AppMenu::href($tab['route'], session('app_bundle')) }}" @class(['on' => $tab['active']]) @if ($tab['active']) aria-current="page" @endif>{{ $tab['label'] }}</a>
        @endforeach
    </nav>
@endif
