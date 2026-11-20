@php
    use App\Models\SiteRegistration;

    $klaar = $registration->doneSteps();
    $partners = $agreements->pluck('subcontractor')->unique('id')->values();

    // De tekst van het mailpakket: wat erin moet staan, zodat niemand iets vergeet.
    $regels = collect([
        'Aanmelding bouw '.$project->number().' '.$project->name,
        'Adres: '.(collect([$project->address, $project->city])->filter()->join(', ') ?: 'nog niet bekend'),
        'Opdrachtgever: '.($project->customer?->name ?? 'onbekend'),
        'Contactpersoon op de bouw: '.(collect([$registration->contact_name, $registration->contact_phone])->filter()->join(', ') ?: 'nog niet bekend'),
        '',
        'Onderaannemers op dit werk:',
        ...$partners->map(fn ($partner) => '- '.$partner->name.($partner->coc_number ? ', KvK '.$partner->coc_number : ''))->all(),
        '',
        'Meegestuurde documenten:',
        ...$documents->map(fn ($document) => '- '.$document->name)->all(),
    ])->join("\n");

    $mailto = 'mailto:'.$wkaEmail.'?subject='.rawurlencode('Aanmelding bouw '.$project->number().' '.$project->name).'&body='.rawurlencode($regels);
@endphp

<div class="grid gap-4 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        {{-- De drie aanmeldingen --}}
        <section class="rounded-2xl border border-surface-700 bg-surface-800">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-surface-700 px-5 py-4">
                <div>
                    <h2 class="text-sm font-bold text-ink">Aanmelding <span class="text-brand-500">bouw</span></h2>
                    <p class="mt-0.5 text-xs text-faint">Wie er op de bouw komt, moet vooraf zijn aangemeld. Vul de datum in zodra een aanmelding is gedaan.</p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $klaar === count(SiteRegistration::STEPS) ? 'bg-green-500/15 text-green-300' : 'bg-amber-500/15 text-amber-300' }}">
                    {{ $klaar }} van {{ count(SiteRegistration::STEPS) }} gedaan
                </span>
            </div>

            <form method="POST" action="{{ route('site-registrations.update', $project) }}" class="space-y-4 p-5">
                @csrf
                @method('PATCH')

                <div class="space-y-2">
                    @foreach (SiteRegistration::STEPS as $field => [$label, $uitleg])
                        <div class="flex flex-wrap items-center gap-3 rounded-xl border border-surface-700 bg-surface-850 p-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $registration->{$field} ? 'bg-green-500/15 text-green-300' : 'bg-surface-700 text-faint' }}">
                                <x-icon name="check" class="h-4 w-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-ink">{{ $label }}</span>
                                <span class="block text-xs text-faint">{{ $uitleg }}</span>
                            </span>
                            <input type="date" name="{{ $field }}" value="{{ old($field, $registration->{$field}?->toDateString()) }}" aria-label="Datum {{ $label }}"
                                   class="rounded-xl border-surface-600 text-sm">
                        </div>
                    @endforeach
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-xs text-muted">Contactpersoon op de bouw
                        <input type="text" name="contact_name" value="{{ old('contact_name', $registration->contact_name) }}" maxlength="255" class="mt-1 w-full rounded-xl border-surface-600 text-sm">
                    </label>
                    <label class="block text-xs text-muted">Telefoon
                        <input type="text" name="contact_phone" value="{{ old('contact_phone', $registration->contact_phone) }}" maxlength="50" class="mt-1 w-full rounded-xl border-surface-600 text-sm">
                    </label>
                </div>

                <label class="block text-xs text-muted">Bijzonderheden
                    <textarea name="notes" rows="2" maxlength="2000" class="mt-1 w-full rounded-xl border-surface-600 text-sm">{{ old('notes', $registration->notes) }}</textarea>
                </label>

                <div class="flex justify-end">
                    <button type="submit" class="btn pri">Opslaan</button>
                </div>
            </form>
        </section>

        {{-- Overeenkomst per onderaannemer --}}
        <section class="rounded-2xl border border-surface-700 bg-surface-800">
            <div class="border-b border-surface-700 px-5 py-4">
                <h2 class="text-sm font-bold text-ink">Overeen<span class="text-brand-500">komsten</span></h2>
                <p class="mt-0.5 text-xs text-faint">
                    Per onderaannemer een overeenkomst voor dit project. Hij staat hier zodra er gegund is, en is rond als de getekende versie binnen is.
                </p>
            </div>

            @if ($agreements->isEmpty())
                <p class="p-5 text-sm text-faint">Nog geen onderaannemers op dit project. Zodra een prijsaanvraag is gegund, staat de overeenkomst hier klaar.</p>
            @else
                <div class="tw">
                    <table class="t">
                        <thead>
                            <tr>
                                <th>Onderaannemer</th>
                                <th>Opdracht</th>
                                <th>Dossier</th>
                                <th>Doorlopend werk</th>
                                <th>Overeenkomst</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($agreements as $agreement)
                                @php
                                    $partner = $agreement->subcontractor;
                                    $raam = $partner->activeFramework();
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('subcontractors.show', $partner) }}"><b>{{ $partner->name }}</b></a>
                                        @if ($partner->coc_number)
                                            <span class="sub">KvK {{ $partner->coc_number }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($agreement->rfq)
                                            <a href="{{ route('rfqs.show', $agreement->rfq) }}" class="hover:text-ink">{{ $agreement->rfq->order_number ?? $agreement->rfq->number }}</a>
                                            <span class="sub">{{ $agreement->rfq->title }}</span>
                                        @else
                                            <span class="text-faint">geen aanvraag</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $partner->badgeClasses() }}">{{ $partner->statusLabel() }}</span>
                                    </td>
                                    <td>
                                        @if ($raam)
                                            <span class="{{ $raam->daysLeft() <= 31 ? 'text-amber-300' : 'text-ink2' }}">t/m {{ $raam->ends_on->translatedFormat('j M Y') }}</span>
                                            @if ($raam->daysLeft() <= 31)
                                                <span class="sub">loopt over {{ $raam->daysLeft() }} dagen af</span>
                                            @endif
                                        @else
                                            <span class="text-faint">geen lopende raamovereenkomst</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($agreement->isRecorded())
                                            <span class="text-green-300">Vastgelegd</span>
                                            <span class="sub">{{ $agreement->recorded_at->translatedFormat('j M Y') }}{{ $agreement->recorder ? ', '.$agreement->recorder->name : '' }}</span>
                                        @else
                                            <form method="POST" action="{{ route('project-agreements.record', $agreement) }}"
                                                  onsubmit="return confirm('Vastleggen dat de getekende overeenkomst binnen is?')">
                                                @csrf
                                                <button type="submit" class="btn sm">Vastleggen</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    {{-- Het mailpakket --}}
    <div class="space-y-4">
        <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <h2 class="mb-1 text-sm font-bold text-ink">Mail<span class="text-brand-500">pakket</span></h2>
            <p class="text-xs text-faint">
                Alle documenten van deze bouw in één mail naar {{ $wkaEmail }}. De mail opent in je eigen mailprogramma met de tekst al ingevuld:
                voeg de documenten toe en verstuur hem. Leg daarna hier vast dat hij weg is.
            </p>

            <ul class="mt-3 space-y-1.5 text-sm">
                @forelse ($documents as $document)
                    <li class="flex items-center gap-2 text-ink2">
                        <x-icon name="document-text" class="h-4 w-4 shrink-0 text-faint" />
                        <a href="{{ route('documents.show', $document) }}" class="min-w-0 truncate hover:text-ink">{{ $document->name }}</a>
                    </li>
                @empty
                    <li class="text-faint">Nog geen documenten bij dit project.</li>
                @endforelse
            </ul>

            <a href="{{ $mailto }}" class="btn pri mt-4 w-full">
                <x-icon name="envelope" class="h-4 w-4" /> Mail opstellen
            </a>

            @if ($registration->package_sent_at)
                <p class="mt-3 text-sm text-green-300">
                    Verstuurd op {{ $registration->package_sent_at->translatedFormat('j F Y') }}{{ $registration->sender ? ' door '.$registration->sender->name : '' }}.
                </p>
            @endif

            <form method="POST" action="{{ route('site-registrations.package', $project) }}" class="mt-2">
                @csrf
                <button type="submit" class="w-full rounded-full border border-surface-600 py-2 text-sm font-semibold text-ink transition hover:text-ink">
                    {{ $registration->package_sent_at ? 'Opnieuw verstuurd, datum bijwerken' : 'Pakket is verstuurd' }}
                </button>
            </form>
        </section>
    </div>
</div>
