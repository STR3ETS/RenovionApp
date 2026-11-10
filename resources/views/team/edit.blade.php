<x-layouts.app :title="'Bewerken: '.$user->name">

    <x-page-header eyebrow="Teambeheer" :title="'Bewerken: '.$user->name" />

    <form method="POST" action="{{ route('team.update', $user) }}" class="max-w-xl space-y-5">
        @csrf
        @method('PATCH')

        <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Naam *" name="name" class="sm:col-span-2">
                    <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required class="w-full rounded-xl border-surface-600 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="E-mailadres *" name="email">
                    <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required class="w-full rounded-xl border-surface-600 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Telefoon" name="phone">
                    <input type="text" name="phone" id="phone" value="{{ old('phone', $user->phone) }}" class="w-full rounded-xl border-surface-600 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Rol *" name="role">
                    <select name="role" id="role" required @disabled($user->is(auth()->user()))
                            class="w-full rounded-xl border-surface-600 text-sm focus:border-brand-500 focus:ring-brand-500 disabled:bg-surface-750 disabled:text-slate-400">
                        @foreach (\App\Enums\UserRole::internal() as $role)
                            <option value="{{ $role->value }}" @selected(old('role', $user->role->value) === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                    @if ($user->is(auth()->user()))
                        <p class="mt-1 text-xs text-slate-500">Je kunt je eigen rol niet wijzigen.</p>
                        <input type="hidden" name="role" value="{{ $user->role->value }}">
                    @endif
                </x-field>
                <x-field label="Nieuw wachtwoord (leeg = ongewijzigd)" name="password">
                    <input type="password" name="password" id="password" minlength="8" class="w-full rounded-xl border-surface-600 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
            </div>
        </section>

        <div class="flex gap-2">
            <button type="submit" class="rounded-xl bg-brand-600 px-6 py-3 text-sm font-bold text-white transition hover:bg-brand-500">Opslaan</button>
            <a href="{{ route('team.index') }}" class="rounded-xl border border-surface-600 bg-surface-800 px-6 py-3 text-sm font-semibold text-slate-300 transition hover:bg-surface-850">Annuleren</a>
        </div>
    </form>

    {{-- Uit het team halen. Staat los van het formulier, zodat Enter in een
         veld nooit per ongeluk iemand verwijdert. --}}
    @unless ($user->is(auth()->user()))
        <section class="mt-8 max-w-xl rounded-2xl border border-red-500/25 bg-red-500/5 p-5">
            @if ($user->active)
                <h2 class="text-sm font-bold text-white">Uit het team halen</h2>
                <p class="mt-1 text-sm text-slate-400">
                    {{ $user->name }} kan daarna niet meer inloggen. Staan er klussen of meldingen op zijn naam,
                    dan blijft dat werk bewaard en wordt het account gedeactiveerd in plaats van verwijderd.
                </p>
                <form method="POST" action="{{ route('team.destroy', $user) }}" class="mt-4"
                      onsubmit="return confirm(@js($user->name.' uit het team halen? Dit account kan daarna niet meer inloggen.'))">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-full border border-red-500/40 bg-red-500/10 px-5 py-2.5 text-sm font-bold text-red-300 transition hover:bg-red-500/20">
                        Verwijderen
                    </button>
                </form>
            @else
                <h2 class="text-sm font-bold text-white">Dit account is gedeactiveerd</h2>
                <p class="mt-1 text-sm text-slate-400">
                    {{ $user->name }} kan niet inloggen. Het werk op zijn naam is bewaard gebleven.
                </p>
                <form method="POST" action="{{ route('team.activate', $user) }}" class="mt-4">
                    @csrf
                    <button type="submit" class="rounded-full border border-surface-600 bg-surface-800 px-5 py-2.5 text-sm font-bold text-slate-200 transition hover:bg-surface-750">
                        Weer activeren
                    </button>
                </form>
            @endif
        </section>
    @endunless

</x-layouts.app>
