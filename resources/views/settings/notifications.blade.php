<x-layouts.app title="Meldingen">

    <x-page-header eyebrow="Instellingen" title="Meldingen" subtitle="Kies welke pushmeldingen je op je telefoon krijgt" />

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">

        {{-- Soorten meldingen: per stuk aan/uit (briefing §19), slaat direct op bij omzetten --}}
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white lg:col-span-2"
                 x-data="{
                     saved: null,
                     failed: null,
                     timer: null,
                     async toggle(type, input) {
                         this.failed = null;
                         try {
                             await patchJson(@js(route('notification-settings.update')), { type, enabled: input.checked });
                             this.saved = type;
                         } catch {
                             input.checked = !input.checked;
                             this.saved = null;
                             this.failed = type;
                         }
                         clearTimeout(this.timer);
                         this.timer = setTimeout(() => { this.saved = null; this.failed = null; }, 2500);
                     },
                 }">
            <div class="divide-y divide-gray-100">
                @foreach ($types as $type)
                    <label class="flex cursor-pointer items-start gap-3 p-4">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-navy-900">
                            <x-icon :name="$type->icon()" class="h-5 w-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-navy-900">{{ $type->label() }}</span>
                            <span class="mt-0.5 block text-xs text-gray-500">{{ $type->description() }}</span>
                            <span x-show="saved === @js($type->value)" x-cloak x-transition.opacity class="mt-1 flex items-center gap-1 text-xs font-semibold text-green-700">
                                <x-icon name="check" class="h-3.5 w-3.5" /> Opgeslagen
                            </span>
                            <span x-show="failed === @js($type->value)" x-cloak class="mt-1 block text-xs font-semibold text-red-600">
                                Niet opgeslagen, probeer het opnieuw.
                            </span>
                        </span>
                        <input type="checkbox" class="peer sr-only" @checked(auth()->user()->wantsPush($type))
                               @change="toggle(@js($type->value), $event.target)">
                        <span class="relative mt-1 h-6 w-11 shrink-0 rounded-full bg-gray-200 transition peer-checked:bg-brand-500 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500 peer-focus-visible:ring-offset-2 after:absolute after:top-0.5 after:left-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></span>
                    </label>
                @endforeach
            </div>
        </section>

        {{-- Dit toestel: toestemming op de telefoon en een testmelding --}}
        <section class="rounded-2xl border border-gray-200 bg-white p-5"
                 x-data="{ native: Boolean(window.Capacitor?.isNativePlatform?.()), status: null }"
                 x-init="if (native) window.Capacitor.Plugins.FirebaseMessaging?.checkPermissions().then((result) => status = result.receive)">
            <h2 class="text-sm font-bold text-navy-900">Dit toestel</h2>

            <template x-if="native && status === 'granted'">
                <p class="mt-2 flex items-center gap-2 text-sm text-green-700">
                    <x-signal-dot color="green" /> Deze telefoon ontvangt meldingen.
                </p>
            </template>
            <template x-if="native && status && status !== 'granted'">
                <div class="mt-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-800">
                    <p class="font-semibold">Meldingen staan uit voor Renovion op deze telefoon.</p>
                    <p class="mt-1 text-xs">Zet ze aan via de instellingen van je telefoon. Android: Instellingen, Apps, Renovion, Meldingen. iPhone: Instellingen, Renovion, Berichtgeving.</p>
                </div>
            </template>
            <template x-if="!native">
                <p class="mt-2 text-sm text-gray-500">
                    @if ($devices > 0)
                        Je ontvangt pushmeldingen op {{ $devices === 1 ? '1 telefoon' : $devices.' telefoons' }}.
                    @else
                        Log in de Renovion-app op je telefoon in en sta meldingen toe om pushmeldingen te krijgen.
                    @endif
                </p>
            </template>

            @if ($devices > 0)
                <form method="POST" action="{{ route('notification-settings.test') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-navy-900 transition hover:bg-gray-50">
                        <x-icon name="paper-airplane" class="h-4 w-4" />
                        Testmelding sturen
                    </button>
                </form>
            @endif
        </section>
    </div>

</x-layouts.app>
