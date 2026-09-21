<x-layouts.app title="Nieuw project">

    <x-page-header title="Nieuw project" />

    <form method="POST" action="{{ route('projects.store') }}" class="max-w-2xl space-y-5"
          x-data="{ bestaand: true }">
        @csrf

        <section class="rounded-2xl border border-gray-200 bg-white p-5">
            <h2 class="mb-4 text-sm font-bold text-navy-900">Klant</h2>

            <div class="mb-4 flex rounded-lg border border-gray-300 p-0.5 text-xs font-semibold">
                <button type="button" @click="bestaand = true" :class="bestaand ? 'bg-navy-900 text-white' : 'text-gray-600'" class="flex-1 rounded-md px-3 py-2">Bestaande klant</button>
                <button type="button" @click="bestaand = false" :class="!bestaand ? 'bg-navy-900 text-white' : 'text-gray-600'" class="flex-1 rounded-md px-3 py-2">Nieuwe klant</button>
            </div>

            <div x-show="bestaand">
                <x-field label="Klant" name="customer_id">
                    <select name="customer_id" id="customer_id" x-bind:disabled="!bestaand" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Kies klant —</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }} ({{ $customer->city ?? '—' }})</option>
                        @endforeach
                    </select>
                </x-field>
            </div>

            <div x-show="!bestaand" x-cloak class="grid gap-4 sm:grid-cols-2">
                <x-field label="Naam *" name="customer_name" class="sm:col-span-2">
                    <input type="text" name="customer_name" id="customer_name" value="{{ old('customer_name') }}" placeholder="Familie Jansen" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="E-mailadres" name="customer_email">
                    <input type="email" name="customer_email" id="customer_email" value="{{ old('customer_email') }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Telefoon" name="customer_phone">
                    <input type="text" name="customer_phone" id="customer_phone" value="{{ old('customer_phone') }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Adres" name="customer_address">
                    <input type="text" name="customer_address" id="customer_address" value="{{ old('customer_address') }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <div class="grid grid-cols-2 gap-4">
                    <x-field label="Postcode" name="customer_postal_code">
                        <input type="text" name="customer_postal_code" id="customer_postal_code" value="{{ old('customer_postal_code') }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </x-field>
                    <x-field label="Plaats" name="customer_city">
                        <input type="text" name="customer_city" id="customer_city" value="{{ old('customer_city') }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </x-field>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5">
            <h2 class="mb-4 text-sm font-bold text-navy-900">Project</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Projectnaam *" name="name" class="sm:col-span-2">
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required placeholder="Badkamer Jansen" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Adres (indien anders dan klant)" name="address">
                    <input type="text" name="address" id="address" value="{{ old('address') }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Plaats" name="city">
                    <input type="text" name="city" id="city" value="{{ old('city') }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Status" name="status">
                    <select name="status" id="status" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @foreach (\App\Enums\ProjectStatus::cases() as $statusOption)
                            <option value="{{ $statusOption->value }}" @selected(old('status', 'voorbereiding') === $statusOption->value)>{{ $statusOption->label() }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Projectleider" name="project_leader_id">
                    <select name="project_leader_id" id="project_leader_id" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Geen —</option>
                        @foreach ($leaders as $leader)
                            <option value="{{ $leader->id }}" @selected(old('project_leader_id') == $leader->id)>{{ $leader->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Startdatum" name="start_date">
                    <input type="date" name="start_date" id="start_date" value="{{ old('start_date') }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Verwachte oplevering" name="end_date_expected">
                    <input type="date" name="end_date_expected" id="end_date_expected" value="{{ old('end_date_expected') }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Projectwaarde (€)" name="value">
                    <input type="number" name="value" id="value" value="{{ old('value') }}" min="0" step="0.01" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Aanbetaling (€)" name="deposit_amount">
                    <input type="number" name="deposit_amount" id="deposit_amount" value="{{ old('deposit_amount') }}" min="0" step="0.01" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Notities" name="notes" class="sm:col-span-2">
                    <textarea name="notes" id="notes" rows="3" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('notes') }}</textarea>
                </x-field>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5">
            <h2 class="mb-4 text-sm font-bold text-navy-900">Vakmensen op dit project</h2>
            <div class="grid gap-2 sm:grid-cols-2">
                @forelse ($vakmensen as $vakman)
                    <label class="flex items-center gap-2 rounded-xl border border-gray-200 p-3 text-sm font-medium text-navy-900 transition has-checked:border-brand-400 has-checked:bg-brand-50">
                        <input type="checkbox" name="craftsmen[]" value="{{ $vakman->id }}"
                               @checked(in_array($vakman->id, old('craftsmen', [])))
                               class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                        {{ $vakman->name }}
                    </label>
                @empty
                    <p class="text-sm text-gray-400 sm:col-span-2">Nog geen vakmensen — voeg ze toe via Team.</p>
                @endforelse
            </div>
        </section>

        <div class="flex gap-2">
            <button type="submit" class="rounded-xl bg-brand-600 px-6 py-3 text-sm font-bold text-white transition hover:bg-brand-500">Project aanmaken</button>
            <a href="{{ route('projects.index') }}" class="rounded-xl border border-gray-300 bg-white px-6 py-3 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Annuleren</a>
        </div>
    </form>

</x-layouts.app>
