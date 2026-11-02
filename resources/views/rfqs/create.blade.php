<x-layouts.app title="Nieuwe prijsaanvraag">
    <x-page-header title="Nieuwe prijsaanvraag" eyebrow="Mensen en partners" category="klant"
                   subtitle="Kies het hoofdstuk, dan pakt het systeem het begrote bedrag als budget." />

    <form method="POST" action="{{ route('rfqs.store') }}" class="max-w-2xl space-y-4">
        @csrf

        <div class="space-y-4 rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <x-field label="Project" name="project_id">
                <select name="project_id" id="project_id" required
                        class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected(old('project_id', $projectId) == $project->id)>
                            {{ $project->name }}{{ $project->customer ? ', '.$project->customer->name : '' }}
                        </option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Wat zet je uit?" name="title">
                <input type="text" name="title" id="title" required value="{{ old('title') }}"
                       placeholder="Bijvoorbeeld: Tegelwerk badkamer en toilet"
                       class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
            </x-field>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Hoofdstuk uit de begroting" name="chapter_code">
                    <select name="chapter_code" id="chapter_code"
                            class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Geen hoofdstuk, budget zelf invullen</option>
                        @foreach ($chapters as $code => $name)
                            <option value="{{ $code }}" @selected(old('chapter_code') === $code)>{{ $code }} {{ $name }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Budget" name="budget">
                    <input type="number" step="0.01" min="0" name="budget" id="budget" value="{{ old('budget') }}"
                           placeholder="Laat leeg voor het hoofdstuktotaal"
                           class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
                </x-field>

                <x-field label="Deadline voor biedingen" name="deadline">
                    <input type="date" name="deadline" id="deadline" value="{{ old('deadline', today()->addWeek()->toDateString()) }}"
                           class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                </x-field>

                <x-field label="Gewenste start" name="starts_on">
                    <input type="date" name="starts_on" id="starts_on" value="{{ old('starts_on') }}"
                           class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                </x-field>
            </div>

            <x-field label="Werkomschrijving" name="scope">
                <textarea name="scope" id="scope" rows="6" placeholder="Wat moet er gebeuren, wat is inbegrepen en wat niet?"
                          class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">{{ old('scope') }}</textarea>
            </x-field>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('rfqs.index') }}" class="rounded-full border border-surface-600 px-5 py-2.5 text-sm font-semibold text-slate-300">Annuleren</a>
            <button type="submit" class="flex items-center gap-1.5 rounded-full bg-brand-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
                <x-icon name="arrow-right" class="h-4 w-4" /> Aanmaken
            </button>
        </div>
    </form>
</x-layouts.app>
