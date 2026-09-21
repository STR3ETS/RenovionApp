<x-layouts.app title="Nieuw teamlid">

    <x-page-header title="Nieuw teamlid" />

    <form method="POST" action="{{ route('team.store') }}" class="max-w-xl space-y-5">
        @csrf

        <section class="rounded-2xl border border-gray-200 bg-white p-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Naam *" name="name" class="sm:col-span-2">
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="E-mailadres *" name="email">
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Telefoon" name="phone">
                    <input type="text" name="phone" id="phone" value="{{ old('phone') }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Rol *" name="role">
                    <select name="role" id="role" required class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @foreach (\App\Enums\UserRole::cases() as $role)
                            <option value="{{ $role->value }}" @selected(old('role', 'vakman') === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Wachtwoord * (min. 8 tekens)" name="password">
                    <input type="password" name="password" id="password" required minlength="8" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
            </div>
        </section>

        <div class="flex gap-2">
            <button type="submit" class="rounded-xl bg-brand-600 px-6 py-3 text-sm font-bold text-white transition hover:bg-brand-500">Teamlid toevoegen</button>
            <a href="{{ route('team.index') }}" class="rounded-xl border border-gray-300 bg-white px-6 py-3 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Annuleren</a>
        </div>
    </form>

</x-layouts.app>
