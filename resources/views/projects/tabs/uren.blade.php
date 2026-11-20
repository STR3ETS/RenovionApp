@php
    use App\Models\Project;
    $eur = fn (float $v) => '€ '.number_format($v, 2, ',', '.');
    $num = fn (float $v) => rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
    $canCrm = auth()->user()->can('manage-crm');
    $maySign = auth()->user()->role->isManagement() || auth()->user()->role === \App\Enums\UserRole::Projectleider;
    $regie = $statement->isRegie();
@endphp

<div class="space-y-4">
    @if ($regie)
        <x-kpi-row>
            <x-kpi dark label="Nog te factureren" :value="$eur($statement->toInvoice())" sub="exclusief btw" />
            <x-kpi label="Uren op de staat" :value="$num($statement->hours())" :sub="$statement->perDay() ? $num($statement->units($statement->hours())).' dagen van acht uur' : 'tegen '.$eur($statement->rate()).' per uur'" />
            <x-kpi label="Materiaal" :value="$eur($statement->materialsTotal())" :sub="$statement->materials->count().' '.($statement->materials->count() === 1 ? 'regel' : 'regels')" />
            <x-kpi label="Al gefactureerd" :value="$eur($statement->invoiced())" sub="staat niet nog eens op de volgende factuur" />
        </x-kpi-row>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- De uren --}}
            <section class="rounded-2xl border border-surface-700 bg-surface-800">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-surface-700 px-5 py-4">
                    <div>
                        <h2 class="text-sm font-bold text-ink">Uren<span class="text-brand-500">staat</span></h2>
                        <p class="mt-0.5 text-xs text-faint">
                            {{ $regie
                                ? 'Regie: deze uren rekenen we af. Uren uit een goedgekeurde werkbon komen er vanzelf bij.'
                                : 'Aangenomen werk: de uren staan er ter informatie. Uren uit een goedgekeurde werkbon komen er vanzelf bij.' }}
                        </p>
                    </div>
                    <a href="{{ route('hours.print', $project) }}" target="_blank" rel="noopener"
                       class="btn sm">Uitdraaien</a>
                </div>

                @if ($statement->entries->isEmpty())
                    <p class="p-5 text-sm text-faint">Nog geen uren op dit project.</p>
                @else
                    <div class="tw">
                        <table class="t">
                            <thead>
                                <tr>
                                    <th>Datum</th>
                                    <th>Wie</th>
                                    <th>Omschrijving</th>
                                    <th class="r">Uren</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($statement->entries as $entry)
                                    <tr>
                                        <td class="whitespace-nowrap">{{ $entry->date->translatedFormat('D j M') }}</td>
                                        <td>{{ $entry->user?->name ?? 'onbekend' }}</td>
                                        <td>
                                            {{ $entry->description ?: 'Geen omschrijving' }}
                                            @if ($entry->isInvoiced())
                                                <span class="ml-1 rounded-full bg-lead-500/15 px-2 py-0.5 text-[10px] font-bold text-lead-300">Gefactureerd</span>
                                            @endif
                                        </td>
                                        <td class="r"><b>{{ $num((float) $entry->hours) }}</b></td>
                                        <td class="r">
                                            @if (! $entry->isInvoiced() && ($canCrm || ($entry->user_id === auth()->id() && $entry->work_order_id === null)))
                                                <form method="POST" action="{{ route('hours.destroy', $entry) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-xs text-faint hover:text-red-300">Verwijderen</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3"><b>Totaal</b></td>
                                    <td class="r"><b>{{ $num($statement->hours()) }}</b></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif

                <form method="POST" action="{{ route('hours.store', $project) }}" class="grid gap-3 border-t border-surface-700 p-5 sm:grid-cols-[auto_auto_minmax(0,1fr)_auto] sm:items-end">
                    @csrf
                    <label class="block text-xs text-muted">Datum
                        <input type="date" name="date" value="{{ old('date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required class="mt-1 w-full rounded-xl border-surface-600 text-sm">
                    </label>
                    <label class="block text-xs text-muted">Uren
                        <input type="text" inputmode="decimal" name="hours" value="{{ old('hours') }}" required placeholder="7,5" class="mt-1 w-full rounded-xl border-surface-600 text-sm sm:w-24">
                    </label>
                    <label class="block text-xs text-muted">Omschrijving
                        <input type="text" name="description" value="{{ old('description') }}" maxlength="255" placeholder="Wat is er gedaan" class="mt-1 w-full rounded-xl border-surface-600 text-sm">
                    </label>
                    <button type="submit" class="btn pri">Uren boeken</button>
                    @if ($canCrm)
                        <label class="block text-xs text-muted sm:col-span-2">Voor wie
                            <select name="user_id" class="mt-1 w-full rounded-xl border-surface-600 text-sm">
                                @foreach ($people as $person)
                                    <option value="{{ $person->id }}" @selected($person->id === auth()->id())>{{ $person->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    @error('date')<p class="text-xs font-medium text-red-400 sm:col-span-4">{{ $message }}</p>@enderror
                    @error('hours')<p class="text-xs font-medium text-red-400 sm:col-span-4">{{ $message }}</p>@enderror
                </form>
            </section>

            {{-- Materiaal bij regie --}}
            @if ($regie && $canCrm)
                <section class="rounded-2xl border border-surface-700 bg-surface-800">
                    <div class="border-b border-surface-700 px-5 py-4">
                        <h2 class="text-sm font-bold text-ink">Mate<span class="text-brand-500">riaal</span></h2>
                        <p class="mt-0.5 text-xs text-faint">Wat we naast de uren afrekenen.</p>
                    </div>

                    @if ($statement->materials->isNotEmpty())
                        <div class="tw">
                            <table class="t">
                                <thead>
                                    <tr>
                                        <th>Omschrijving</th>
                                        <th class="r">Aantal</th>
                                        <th class="r">Prijs</th>
                                        <th class="r">Totaal</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($statement->materials as $material)
                                        <tr>
                                            <td>
                                                {{ $material->description }}
                                                @if ($material->isInvoiced())
                                                    <span class="ml-1 rounded-full bg-lead-500/15 px-2 py-0.5 text-[10px] font-bold text-lead-300">Gefactureerd</span>
                                                @endif
                                            </td>
                                            <td class="r">{{ $num((float) $material->quantity) }} {{ $material->unit }}</td>
                                            <td class="r">{{ $eur((float) $material->price) }}</td>
                                            <td class="r"><b>{{ $eur($material->total()) }}</b></td>
                                            <td class="r">
                                                @unless ($material->isInvoiced())
                                                    <form method="POST" action="{{ route('regie-materials.destroy', $material) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-xs text-faint hover:text-red-300">Verwijderen</button>
                                                    </form>
                                                @endunless
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('regie-materials.store', $project) }}" class="grid gap-3 border-t border-surface-700 p-5 sm:grid-cols-[minmax(0,1fr)_auto_auto_auto_auto] sm:items-end">
                        @csrf
                        <label class="block text-xs text-muted">Omschrijving
                            <input type="text" name="description" required maxlength="255" class="mt-1 w-full rounded-xl border-surface-600 text-sm">
                        </label>
                        <label class="block text-xs text-muted">Aantal
                            <input type="text" inputmode="decimal" name="quantity" value="1" required class="mt-1 w-full rounded-xl border-surface-600 text-sm sm:w-20">
                        </label>
                        <label class="block text-xs text-muted">Eenheid
                            <input type="text" name="unit" value="st" maxlength="20" class="mt-1 w-full rounded-xl border-surface-600 text-sm sm:w-20">
                        </label>
                        <label class="block text-xs text-muted">Prijs per eenheid
                            <input type="text" inputmode="decimal" name="price" required placeholder="12,50" class="mt-1 w-full rounded-xl border-surface-600 text-sm sm:w-28">
                        </label>
                        <button type="submit" class="btn">Toevoegen</button>
                    </form>
                </section>
            @endif
        </div>

        <div class="space-y-4">
            {{-- Aangenomen of regie --}}
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-1 text-sm font-bold text-ink">Contract<span class="text-brand-500">vorm</span></h2>
                <p class="mb-3 text-xs text-faint">Aangenomen werk heeft een vaste prijs. Bij regie rekenen we uren en materiaal af.</p>

                @if ($canCrm)
                    <form method="POST" action="{{ route('hours.contract', $project) }}" class="space-y-3" x-data="{ vorm: @js($project->contract_form) }">
                        @csrf
                        @method('PATCH')
                        <div class="grid grid-cols-2 gap-2">
                            @foreach (Project::FORMS as $key => $label)
                                <label class="flex cursor-pointer items-center justify-center rounded-xl border px-3 py-2.5 text-center text-sm font-semibold transition"
                                       :class="vorm === '{{ $key }}' ? 'border-brand-500 bg-brand-500/10 text-ink' : 'border-surface-700 text-ink2'">
                                    <input type="radio" name="contract_form" value="{{ $key }}" x-model="vorm" class="sr-only">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        <div x-show="vorm === '{{ Project::FORM_REGIE }}'" x-cloak class="grid grid-cols-2 gap-3">
                            <label class="block text-xs text-muted">Uurtarief
                                <input type="text" inputmode="decimal" name="regie_hourly_rate" value="{{ $project->regie_hourly_rate !== null ? number_format((float) $project->regie_hourly_rate, 2, ',', '') : '' }}"
                                       placeholder="55,00" class="mt-1 w-full rounded-xl border-surface-600 text-sm">
                            </label>
                            <label class="block text-xs text-muted">Of daggeld
                                <input type="text" inputmode="decimal" name="regie_day_rate" value="{{ $project->regie_day_rate !== null ? number_format((float) $project->regie_day_rate, 2, ',', '') : '' }}"
                                       placeholder="leeg is per uur" class="mt-1 w-full rounded-xl border-surface-600 text-sm">
                            </label>
                            <p class="col-span-2 text-xs text-faint">Met een daggeld rekenen we per dag van acht uur, anders per uur.</p>
                        </div>
                        <button type="submit" class="w-full rounded-full border border-surface-600 py-2 text-sm font-semibold text-ink transition hover:border-brand-500 hover:text-ink">Opslaan</button>
                    </form>
                @else
                    <p class="text-sm font-semibold text-ink">{{ Project::FORMS[$project->contract_form] ?? $project->contract_form }}</p>
                @endif
            </section>

            {{-- Aftekenen en factureren --}}
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-1 text-sm font-bold text-ink">Af<span class="text-brand-500">tekenen</span></h2>

                @if ($statement->isSigned())
                    <p class="text-sm {{ $statement->needsResign() ? 'text-amber-300' : 'text-green-300' }}">
                        Afgetekend door {{ $project->hoursSigner?->name ?? 'onbekend' }} op {{ $project->hours_signed_at->translatedFormat('j F Y') }},
                        op {{ $num((float) $project->hours_signed_total) }} uur.
                    </p>
                    @if ($statement->needsResign())
                        <p class="mt-1 text-sm font-semibold text-amber-300">
                            Er staan nu {{ $num($statement->hours()) }} uur. De uren zijn na het aftekenen veranderd: teken opnieuw af.
                        </p>
                    @endif
                @else
                    <p class="text-sm text-muted">Nog niet afgetekend. Aftekenen legt het aantal uren vast.</p>
                @endif

                @if ($maySign && $statement->hours() > 0 && (! $statement->isSigned() || $statement->needsResign()))
                    <form method="POST" action="{{ route('hours.sign', $project) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="btn pri w-full">
                            Aftekenen op {{ $num($statement->hours()) }} uur
                        </button>
                    </form>
                @elseif (! $maySign && ! $statement->isSigned())
                    <p class="mt-2 text-xs text-faint">Aftekenen doet de directie of de projectleider.</p>
                @endif

                @if ($regie && $canCrm)
                    <div class="mt-4 border-t border-surface-700 pt-4">
                        @if ($statement->canInvoice())
                            <form method="POST" action="{{ route('hours.invoice', $project) }}">
                                @csrf
                                <button type="submit" class="btn pri w-full">
                                    Factureren als concept, {{ $eur($statement->toInvoice()) }}
                                </button>
                            </form>
                        @else
                            <p class="text-sm text-muted">{{ $statement->blocker() }}</p>
                        @endif
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>
