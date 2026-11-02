<x-layouts.app title="Chat">

    <x-page-header eyebrow="Communicatie" title="Chat" subtitle="Team en projecten, klanten lezen hier niet mee" />

    <div class="mx-auto max-w-xl overflow-hidden rounded-2xl border border-gray-200 bg-white lg:mx-0">
        @include('chat.partials.channels', ['channels' => $channels, 'user' => $user, 'active' => null])
    </div>

</x-layouts.app>
