<x-layouts.app title="Instellingen">
    <x-page-header title="Instellingen" eyebrow="Organisatie" category="klant"
                   subtitle="Bedrijfsgegevens en de standaardwaarden waarmee het systeem rekent." />

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <form method="POST" action="{{ route('settings.update') }}" class="space-y-4">
                @csrf
                @method('PATCH')

                @foreach ($groups as $group)
                    <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                        <h2 class="mb-4 text-sm font-bold text-white">{{ $group }}</h2>

                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach ($fields as $key => $field)
                                @continue($field['group'] !== $group)
                                <x-field :label="$field['label']" :name="'settings['.$key.']'">
                                    <input type="{{ $field['type'] }}" @if ($field['type'] === 'number') step="0.01" @endif
                                           name="settings[{{ $key }}]" id="settings[{{ $key }}]"
                                           value="{{ $values[$key] }}"
                                           class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                                    @if ($field['hint'])
                                        <p class="mt-1 text-xs text-slate-500">{{ $field['hint'] }}</p>
                                    @endif
                                </x-field>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <button type="submit" class="rounded-full bg-brand-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
                    Instellingen opslaan
                </button>
            </form>
        </div>

        <div class="space-y-4">
            {{-- Wat in .env hoort: alleen of het er is, nooit de waarde --}}
            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-1 text-sm font-bold text-white">Omgeving</h2>
                <p class="mb-4 text-xs text-slate-500">
                    Sleutels en tokens staan in .env, niet in de database. Hier zie je alleen of ze er zijn.
                </p>

                <div class="space-y-3">
                    @foreach ($environment as $item)
                        <div class="flex items-start gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full {{ $item['ready'] ? 'bg-green-500/20 text-green-300' : 'bg-amber-500/20 text-amber-300' }}">
                                <x-icon :name="$item['ready'] ? 'check' : 'x-mark'" class="h-3.5 w-3.5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <b class="block text-sm font-semibold text-white">{{ $item['label'] }}</b>
                                <small class="block text-xs {{ $item['ready'] ? 'text-slate-500' : 'text-amber-300' }}">
                                    {{ $item['ready'] ? 'Ingesteld' : $item['about'] }}
                                </small>
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-1 text-sm font-bold text-white">Modules</h2>
                <p class="mb-4 text-xs text-slate-500">Aan of uit via .env, daarna de caches verversen.</p>

                <div class="space-y-2">
                    @foreach ($modules as $name => $enabled)
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="text-slate-200">{{ $name }}</span>
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $enabled ? 'bg-green-500/15 text-green-300' : 'bg-white/10 text-slate-400' }}">
                                {{ $enabled ? 'Aan' : 'Uit' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-2 text-sm font-bold text-white">Hulp</h2>
                <div class="space-y-2 text-sm">
                    <a href="{{ route('onboarding') }}" class="flex items-center gap-2 text-slate-200 transition hover:text-white">
                        <x-icon name="rocket-launch" class="h-4 w-4 text-slate-500" /> Aan de slag
                    </a>
                    <a href="{{ route('docs.api') }}" class="flex items-center gap-2 text-slate-200 transition hover:text-white">
                        <x-icon name="globe-alt" class="h-4 w-4 text-slate-500" /> API voor aanvragen
                    </a>
                    <a href="{{ route('styleguide') }}" class="flex items-center gap-2 text-slate-200 transition hover:text-white">
                        <x-icon name="light-bulb" class="h-4 w-4 text-slate-500" /> Stijlgids
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
