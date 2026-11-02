@php
    use App\Support\CertificateCatalogue;
@endphp

<x-layouts.app :title="$subcontractor->name">
    <div class="mb-4">
        <a href="{{ route('subcontractors.index') }}" class="flex items-center gap-1 text-sm font-semibold text-slate-400 transition hover:text-white">
            <x-icon name="chevron-left" class="h-4 w-4" /> Onderaannemers
        </a>
    </div>

    <x-page-header :title="$subcontractor->name" eyebrow="Partnerdossier" category="klant"
                   :subtitle="collect([$subcontractor->contact_name, $subcontractor->city, $subcontractor->coc_number ? 'KvK '.$subcontractor->coc_number : null])->filter()->join(', ')">
        <span class="rounded-full px-3 py-1.5 text-xs font-bold {{ $subcontractor->badgeClasses() }}">{{ $subcontractor->statusLabel() }}</span>
    </x-page-header>

    @if ($subcontractor->blocked)
        <div class="mb-4 flex items-start gap-3 rounded-2xl border border-red-500/25 bg-red-500/10 p-4">
            <x-icon name="lock-closed" class="h-5 w-5 shrink-0 text-red-300" />
            <div>
                <p class="text-sm font-bold text-white">Geblokkeerd</p>
                <p class="mt-0.5 text-sm text-slate-300">{{ $subcontractor->blocked_reason ?: 'Geen reden vastgelegd.' }}</p>
            </div>
        </div>
    @elseif (! $dossier['complete'])
        <div class="mb-4 flex items-start gap-3 rounded-2xl border border-amber-500/25 bg-amber-500/10 p-4">
            <x-icon name="exclamation-triangle" class="h-5 w-5 shrink-0 text-amber-300" />
            <p class="text-sm text-slate-200">
                <b class="text-white">{{ count($dossier['open']) }} {{ count($dossier['open']) === 1 ? 'punt staat' : 'punten staan' }} nog open.</b>
                Deze partij kan nog geen uitnodiging of opdracht krijgen.
            </p>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Het dossier --}}
            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-1 text-sm font-bold text-white">Dossier<span class="text-brand-500">eisen</span></h2>
                <p class="mb-4 text-xs text-slate-500">Alles moet in orde zijn voordat iemand van deze partij de bouwplaats op gaat.</p>

                <div class="space-y-2">
                    @foreach ($dossier['items'] as $item)
                        <div class="flex items-center gap-3 rounded-xl border border-surface-700 bg-surface-850 p-3">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ CertificateCatalogue::stateClasses($item['state']) }}">
                                <x-icon :name="$item['state'] === 'ok' ? 'check' : 'x-mark'" class="h-3.5 w-3.5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <b class="block truncate text-sm font-semibold text-white">{{ $item['label'] }}</b>
                                <small class="block text-xs text-slate-500">
                                    {{ CertificateCatalogue::stateLabel($item['state']) }}
                                    @if ($item['until'])
                                        , geldig tot {{ $item['until']->translatedFormat('j M Y') }}
                                    @endif
                                </small>
                            </span>
                        </div>
                    @endforeach
                </div>

                <form method="POST" action="{{ route('subcontractor-documents.store', $subcontractor) }}" enctype="multipart/form-data"
                      class="mt-4 space-y-3 border-t border-surface-700 pt-4">
                    @csrf
                    <h3 class="text-sm font-bold text-white">Dossierstuk <span class="text-brand-500">vastleggen</span></h3>

                    <div class="grid gap-2 sm:grid-cols-2">
                        <select name="type" required
                                class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                            <optgroup label="Documenten">
                                @foreach ($documentTypes as $type => $label)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endforeach
                            </optgroup>
                            <optgroup label="Overeenkomsten">
                                @foreach ($agreementTypes as $type => $label)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endforeach
                            </optgroup>
                        </select>
                        <input type="date" name="valid_until" title="Geldig tot"
                               class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <label class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-surface-600 px-3 py-2 text-sm font-semibold text-slate-200">
                            <x-icon name="paper-clip" class="h-4 w-4" /> Bestand
                            <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" hidden>
                        </label>
                        <button type="submit" class="rounded-xl bg-brand-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-600">Vastleggen</button>
                    </div>
                </form>
            </div>

            {{-- Werk dat deze partij deed --}}
            @if ($subcontractor->awardedRfqs->isNotEmpty())
                <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    <h2 class="mb-4 text-sm font-bold text-white">Gegunde <span class="text-brand-500">opdrachten</span></h2>
                    <div class="space-y-2">
                        @foreach ($subcontractor->awardedRfqs as $rfq)
                            <a href="{{ route('rfqs.show', $rfq) }}" class="flex items-center gap-3 rounded-xl px-2 py-2 transition hover:bg-white/5">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-white">{{ $rfq->title }}</span>
                                    <span class="block truncate text-xs text-slate-500">{{ $rfq->order_number }}, {{ $rfq->project->name }}</span>
                                </span>
                                <span class="shrink-0 text-sm font-bold text-white">
                                    EUR {{ number_format((float) ($rfq->winningBid()?->price ?? 0), 0, ',', '.') }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <form method="POST" action="{{ route('subcontractors.update', $subcontractor) }}" class="space-y-3 rounded-2xl border border-surface-700 bg-surface-800 p-5">
                @csrf
                @method('PATCH')
                <h2 class="text-sm font-bold text-white">Gegevens</h2>

                <x-field label="Contactpersoon" name="contact_name">
                    <input type="text" name="contact_name" value="{{ $subcontractor->contact_name }}"
                           class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="E-mailadres" name="email">
                    <input type="email" name="email" value="{{ $subcontractor->email }}"
                           class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Telefoon" name="phone">
                    <input type="text" name="phone" value="{{ $subcontractor->phone }}"
                           class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="KvK-nummer" name="coc_number">
                    <input type="text" name="coc_number" value="{{ $subcontractor->coc_number }}"
                           class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Uurtarief" name="hourly_rate">
                    <input type="number" step="0.01" name="hourly_rate" value="{{ $subcontractor->hourly_rate }}"
                           class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Beoordeling" name="rating">
                    <select name="rating" class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Nog niet beoordeeld</option>
                        @foreach (range(1, 5) as $score)
                            <option value="{{ $score }}" @selected($subcontractor->rating === $score)>{{ $score }} van 5</option>
                        @endforeach
                    </select>
                </x-field>

                <div>
                    <p class="mb-1.5 text-sm font-medium text-slate-200">Vakken</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($trades as $vak)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="trades[]" value="{{ $vak }}" class="peer sr-only"
                                       @checked(in_array($vak, $subcontractor->trades ?? [], true))>
                                <span class="block rounded-full border border-surface-600 px-2.5 py-1 text-xs font-semibold text-slate-400 transition peer-checked:border-brand-500 peer-checked:bg-brand-500/10 peer-checked:text-white">
                                    {{ $vak }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" name="id_checked" value="1" @checked($subcontractor->id_checked)
                           class="rounded border-surface-600 bg-surface-750 text-brand-500 focus:ring-brand-500">
                    Legitimatie gecontroleerd
                </label>

                <label class="flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" name="blocked" value="1" @checked($subcontractor->blocked)
                           class="rounded border-surface-600 bg-surface-750 text-red-500 focus:ring-red-500">
                    Blokkeren voor nieuw werk
                </label>

                <input type="text" name="blocked_reason" value="{{ $subcontractor->blocked_reason }}" placeholder="Reden van blokkade"
                       class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">

                <button type="submit" class="w-full rounded-xl bg-brand-500 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">Opslaan</button>
            </form>
        </div>
    </div>
</x-layouts.app>
