<x-layouts.app title="Aanvraag bewerken">

    <x-page-header :title="'Bewerken: '.$lead->customer->name" />

    <form method="POST" action="{{ route('leads.update', $lead) }}" class="max-w-2xl space-y-5">
        @csrf
        @method('PATCH')

        <section class="rounded-2xl border border-gray-200 bg-white p-5">
            <h2 class="mb-4 text-sm font-bold text-navy-900">Klantgegevens</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Naam" name="name" class="sm:col-span-2">
                    <input type="text" name="name" id="name" value="{{ old('name', $lead->customer->name) }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="E-mailadres" name="email">
                    <input type="email" name="email" id="email" value="{{ old('email', $lead->customer->email) }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Telefoon {{ blank($lead->customer->phone) ? '(ontbreekt!)' : '' }}" name="phone">
                    <input type="text" name="phone" id="phone" value="{{ old('phone', $lead->customer->phone) }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500 {{ blank($lead->customer->phone) ? 'border-amber-400 bg-amber-50' : '' }}">
                </x-field>
                <x-field label="Adres" name="address">
                    <input type="text" name="address" id="address" value="{{ old('address', $lead->customer->address) }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <div class="grid grid-cols-2 gap-4">
                    <x-field label="Postcode" name="postal_code">
                        <input type="text" name="postal_code" id="postal_code" value="{{ old('postal_code', $lead->customer->postal_code) }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </x-field>
                    <x-field label="Plaats" name="city">
                        <input type="text" name="city" id="city" value="{{ old('city', $lead->customer->city) }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </x-field>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5">
            <h2 class="mb-4 text-sm font-bold text-navy-900">Aanvraag</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Dienst" name="service">
                    <input type="text" name="service" id="service" value="{{ old('service', $lead->service) }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Indicatieve waarde (€)" name="value">
                    <input type="number" name="value" id="value" value="{{ old('value', $lead->value) }}" min="0" step="100" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Omschrijving" name="description" class="sm:col-span-2">
                    <textarea name="description" id="description" rows="3" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('description', $lead->description) }}</textarea>
                </x-field>
                <x-field label="Toegewezen aan" name="assigned_to">
                    <select name="assigned_to" id="assigned_to" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Niet toegewezen —</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(old('assigned_to', $lead->assigned_to) == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Laatste contact" name="last_contact_at">
                    <input type="datetime-local" name="last_contact_at" id="last_contact_at" value="{{ old('last_contact_at', $lead->last_contact_at?->format('Y-m-d\TH:i')) }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Volgende actie" name="next_action">
                    <input type="text" name="next_action" id="next_action" value="{{ old('next_action', $lead->next_action) }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Volgende actie op" name="next_action_at">
                    <input type="datetime-local" name="next_action_at" id="next_action_at" value="{{ old('next_action_at', $lead->next_action_at?->format('Y-m-d\TH:i')) }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
            </div>
        </section>

        <div class="flex gap-2">
            <button type="submit" class="rounded-xl bg-brand-600 px-6 py-3 text-sm font-bold text-white transition hover:bg-brand-500">Opslaan</button>
            <a href="{{ route('leads.show', $lead) }}" class="rounded-xl border border-gray-300 bg-white px-6 py-3 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Annuleren</a>
        </div>
    </form>

</x-layouts.app>
