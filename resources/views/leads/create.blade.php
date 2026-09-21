<x-layouts.app title="Nieuwe aanvraag">

    <x-page-header title="Nieuwe aanvraag" />

    <form method="POST" action="{{ route('leads.store') }}" class="max-w-2xl space-y-5"
          x-data="{ bestaand: {{ $selectedCustomerId ? 'true' : 'false' }} }">
        @csrf

        <section class="rounded-2xl border border-gray-200 bg-white p-5">
            <h2 class="mb-4 text-sm font-bold text-navy-900">Klant</h2>

            <div class="mb-4 flex rounded-lg border border-gray-300 p-0.5 text-xs font-semibold">
                <button type="button" @click="bestaand = false" :class="!bestaand ? 'bg-navy-900 text-white' : 'text-gray-600'" class="flex-1 rounded-md px-3 py-2">Nieuwe klant</button>
                <button type="button" @click="bestaand = true" :class="bestaand ? 'bg-navy-900 text-white' : 'text-gray-600'" class="flex-1 rounded-md px-3 py-2">Bestaande klant</button>
            </div>

            <div x-show="bestaand" x-cloak>
                <x-field label="Klant" name="customer_id">
                    <select name="customer_id" id="customer_id" x-bind:disabled="!bestaand" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Kies klant —</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected($selectedCustomerId === $customer->id)>{{ $customer->name }} ({{ $customer->city ?? '—' }})</option>
                        @endforeach
                    </select>
                </x-field>
            </div>

            <div x-show="!bestaand" class="grid gap-4 sm:grid-cols-2">
                <x-field label="Naam *" name="name" class="sm:col-span-2">
                    <input type="text" name="name" id="name" value="{{ old('name') }}" placeholder="Familie Jansen" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="E-mailadres" name="email">
                    <input type="email" name="email" id="email" value="{{ old('email') }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Telefoon" name="phone">
                    <input type="text" name="phone" id="phone" value="{{ old('phone') }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Adres" name="address">
                    <input type="text" name="address" id="address" value="{{ old('address') }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <div class="grid grid-cols-2 gap-4">
                    <x-field label="Postcode" name="postal_code">
                        <input type="text" name="postal_code" id="postal_code" value="{{ old('postal_code') }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </x-field>
                    <x-field label="Plaats" name="city">
                        <input type="text" name="city" id="city" value="{{ old('city') }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </x-field>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5">
            <h2 class="mb-4 text-sm font-bold text-navy-900">Aanvraag</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Dienst" name="service">
                    <input type="text" name="service" id="service" value="{{ old('service') }}" placeholder="Complete renovatie" list="diensten" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <datalist id="diensten">
                        <option value="Complete renovatie"></option>
                        <option value="Badkamerrenovatie"></option>
                        <option value="Toiletrenovatie"></option>
                        <option value="Stucwerk"></option>
                        <option value="Schilderwerk"></option>
                        <option value="Tegelwerk"></option>
                        <option value="Timmerwerk"></option>
                    </datalist>
                </x-field>
                <x-field label="Indicatieve waarde (€)" name="value">
                    <input type="number" name="value" id="value" value="{{ old('value') }}" min="0" step="100" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Omschrijving" name="description" class="sm:col-span-2">
                    <textarea name="description" id="description" rows="3" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('description') }}</textarea>
                </x-field>
                <x-field label="Toegewezen aan" name="assigned_to">
                    <select name="assigned_to" id="assigned_to" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Niet toegewezen —</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(old('assigned_to') == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Volgende actie op" name="next_action_at">
                    <input type="datetime-local" name="next_action_at" id="next_action_at" value="{{ old('next_action_at') }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Volgende actie" name="next_action" class="sm:col-span-2">
                    <input type="text" name="next_action" id="next_action" value="{{ old('next_action') }}" placeholder="Terugbellen voor intake" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
            </div>
        </section>

        <div class="flex gap-2">
            <button type="submit" class="rounded-xl bg-brand-600 px-6 py-3 text-sm font-bold text-white transition hover:bg-brand-500">Aanvraag opslaan</button>
            <a href="{{ route('leads.index') }}" class="rounded-xl border border-gray-300 bg-white px-6 py-3 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Annuleren</a>
        </div>
    </form>

</x-layouts.app>
