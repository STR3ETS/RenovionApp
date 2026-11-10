<x-layouts.app title="Team">

    @nativeapp
        {{-- In de iOS/Android-app de kanalenlijst in Renovion OS-stijl, gesorteerd op het laatste bericht. --}}
        @include('chat.partials.channels-app', ['channels' => $channels, 'user' => $user])
    @else
    <x-page-header eyebrow="Communicatie" title="Team" subtitle="Teamchat en projectcommunicatie - klantberichten blijven hier altijd buiten." />

    <div class="mx-auto max-w-xl rounded-2xl border border-surface-700 bg-surface-800 p-4 lg:mx-0">
        @include('chat.partials.channels', ['channels' => $channels, 'user' => $user, 'active' => null])
    </div>
    @endnativeapp

</x-layouts.app>
