<x-layouts.app title="Team">

    <x-page-header title="Team" subtitle="Teamchat en projectcommunicatie — klantberichten blijven hier altijd buiten." />

    <div class="mx-auto max-w-xl rounded-2xl border border-gray-200 bg-white p-4 lg:mx-0">
        @include('chat.partials.channels', ['channels' => $channels, 'user' => $user, 'active' => null])
    </div>

</x-layouts.app>
