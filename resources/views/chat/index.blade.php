<x-layouts.app title="Teamchat">

    {{-- Alleen als er nog geen enkel gesprek is; anders opent de chat meteen het laatste gesprek --}}
    <div class="wa showlist">
        @include('chat.partials.list', ['channels' => $channels, 'user' => $user, 'active' => null])

        <section class="wa-main">
            <div class="wa-msgs">
                <div class="wa-sys">Nog geen gesprekken. Maak een kanaal aan met de plus linksboven.</div>
            </div>
        </section>
    </div>

</x-layouts.app>
