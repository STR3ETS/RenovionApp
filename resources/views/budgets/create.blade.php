<x-layouts.app title="Nieuwe begroting">
    <x-page-header title="Nieuwe begroting" eyebrow="Calculatie" category="calc"
                   subtitle="Kies het dossier en het skelet. De hoofdstukken, de staart en het betaalschema staan dan al klaar." />

    <form method="POST" action="{{ route('budgets.store') }}" class="max-w-2xl space-y-4">
        @csrf

        <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <x-field label="Naam van de begroting" name="name">
                <input type="text" name="name" id="name" required autofocus
                       value="{{ old('name') }}" placeholder="Bijvoorbeeld: Badkamerrenovatie Familie de Groot"
                       class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
            </x-field>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <x-field label="Project" name="project_id">
                    <select name="project_id" id="project_id"
                            class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Nog geen project</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id', $projectId) == $project->id)>
                                {{ $project->name }}{{ $project->customer ? ', '.$project->customer->name : '' }}
                            </option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Of een aanvraag" name="lead_id">
                    <select name="lead_id" id="lead_id"
                            class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Geen aanvraag</option>
                        @foreach ($leads as $lead)
                            <option value="{{ $lead->id }}" @selected(old('lead_id', $leadId) == $lead->id)>
                                {{ $lead->customer?->name ?? $lead->name }}
                            </option>
                        @endforeach
                    </select>
                </x-field>
            </div>
        </div>

        <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <h2 class="mb-4 text-sm font-bold text-white">Reken<span class="text-brand-500">instellingen</span></h2>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-field label="Uurtarief" name="hourly_rate">
                    <input type="number" step="0.01" min="1" name="hourly_rate" id="hourly_rate" value="{{ old('hourly_rate', 55) }}"
                           class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                </x-field>

                <x-field label="Btw in procenten" name="vat_pct">
                    <input type="number" step="0.01" min="0" max="50" name="vat_pct" id="vat_pct" value="{{ old('vat_pct', 21) }}"
                           class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                </x-field>

                <x-field label="Oppervlak in m2" name="floor_area">
                    <input type="number" step="0.01" min="0" name="floor_area" id="floor_area" value="{{ old('floor_area') }}"
                           placeholder="Voor prijs per m2"
                           class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
                </x-field>

                <x-field label="Skelet" name="template">
                    <select name="template" id="template"
                            class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Kies automatisch</option>
                        @foreach ($templates as $template)
                            <option value="{{ $template }}" @selected(old('template') === $template)>{{ ucfirst($template) }}</option>
                        @endforeach
                    </select>
                </x-field>
            </div>

            <x-field label="Locatie" name="location" class="mt-4">
                <input type="text" name="location" id="location" value="{{ old('location') }}"
                       placeholder="Laat leeg om het adres van het project te gebruiken"
                       class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
            </x-field>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('budgets.index') }}" class="rounded-full border border-surface-600 px-5 py-2.5 text-sm font-semibold text-slate-300">Annuleren</a>
            <button type="submit" class="flex items-center gap-1.5 rounded-full bg-brand-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
                <x-icon name="arrow-right" class="h-4 w-4" /> Begroting openen
            </button>
        </div>
    </form>
</x-layouts.app>
