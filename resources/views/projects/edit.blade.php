<x-layouts.app :title="'Bewerken: '.$project->name">

    <x-page-header :title="'Bewerken: '.$project->name" :subtitle="$project->customer->name" />

    <form method="POST" action="{{ route('projects.update', $project) }}" class="max-w-2xl space-y-5">
        @csrf
        @method('PATCH')

        <section class="rounded-2xl border border-gray-200 bg-white p-5">
            <h2 class="mb-4 text-sm font-bold text-navy-900">Project</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Naam" name="name" class="sm:col-span-2">
                    <input type="text" name="name" id="name" value="{{ old('name', $project->name) }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Adres" name="address">
                    <input type="text" name="address" id="address" value="{{ old('address', $project->address) }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Plaats" name="city">
                    <input type="text" name="city" id="city" value="{{ old('city', $project->city) }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Startdatum" name="start_date">
                    <input type="date" name="start_date" id="start_date" value="{{ old('start_date', $project->start_date?->toDateString()) }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Verwachte oplevering" name="end_date_expected">
                    <input type="date" name="end_date_expected" id="end_date_expected" value="{{ old('end_date_expected', $project->end_date_expected?->toDateString()) }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Projectleider" name="project_leader_id">
                    <select name="project_leader_id" id="project_leader_id" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Geen —</option>
                        @foreach ($leaders as $leader)
                            <option value="{{ $leader->id }}" @selected(old('project_leader_id', $project->project_leader_id) == $leader->id)>{{ $leader->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Voortgang (%)" name="progress">
                    <input type="number" name="progress" id="progress" value="{{ old('progress', $project->progress) }}" min="0" max="100" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Notities" name="notes" class="sm:col-span-2">
                    <textarea name="notes" id="notes" rows="3" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('notes', $project->notes) }}</textarea>
                </x-field>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5">
            <h2 class="mb-4 text-sm font-bold text-navy-900">Vakmensen op dit project</h2>
            <div class="grid gap-2 sm:grid-cols-2">
                @forelse ($vakmensen as $vakman)
                    <label class="flex items-center gap-2 rounded-xl border border-gray-200 p-3 text-sm font-medium text-navy-900 transition has-checked:border-brand-400 has-checked:bg-brand-50">
                        <input type="checkbox" name="craftsmen[]" value="{{ $vakman->id }}"
                               @checked(in_array($vakman->id, old('craftsmen', $project->craftsmen->pluck('id')->all())))
                               class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                        {{ $vakman->name }}
                    </label>
                @empty
                    <p class="text-sm text-gray-400 sm:col-span-2">Nog geen vakmensen aangemaakt (gebruikers met rol "Vakman").</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5">
            <h2 class="mb-4 text-sm font-bold text-navy-900">Betalingen</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Projectwaarde (€)" name="value">
                    <input type="number" name="value" id="value" value="{{ old('value', $project->value) }}" min="0" step="0.01" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Aanbetaling (€)" name="deposit_amount">
                    <input type="number" name="deposit_amount" id="deposit_amount" value="{{ old('deposit_amount', $project->deposit_amount) }}" min="0" step="0.01" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Reeds betaald (€)" name="paid_amount">
                    <input type="number" name="paid_amount" id="paid_amount" value="{{ old('paid_amount', $project->paid_amount) }}" min="0" step="0.01" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Volgende betaaltermijn" name="next_payment_due_at">
                    <input type="date" name="next_payment_due_at" id="next_payment_due_at" value="{{ old('next_payment_due_at', $project->next_payment_due_at?->toDateString()) }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <label class="flex items-center gap-2 text-sm font-medium text-navy-900 sm:col-span-2">
                    <input type="hidden" name="deposit_received" value="0">
                    <input type="checkbox" name="deposit_received" value="1" @checked(old('deposit_received', $project->deposit_received_at !== null)) class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                    Aanbetaling ontvangen
                </label>
            </div>
        </section>

        <div class="flex gap-2">
            <button type="submit" class="rounded-xl bg-brand-600 px-6 py-3 text-sm font-bold text-white transition hover:bg-brand-500">Opslaan</button>
            <a href="{{ route('projects.show', $project) }}" class="rounded-xl border border-gray-300 bg-white px-6 py-3 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Annuleren</a>
        </div>
    </form>

</x-layouts.app>
