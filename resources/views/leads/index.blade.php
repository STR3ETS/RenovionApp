@php
    use App\Support\Dossier;

    $eur = fn (float $v) => '€ '.number_format($v, 0, ',', '.');

    // De volgende stap van een aanvraag als pil: te laat, vandaag, later of niets gepland.
    $stap = function ($lead) {
        if ($lead->next_action_at === null) {
            return ['none', 'Geen vervolgstap gepland'];
        }

        $wat = ucfirst($lead->next_action ?? 'Opvolgen');
        $wanneer = $lead->next_action_at->isToday()
            ? 'vandaag '.$lead->next_action_at->format('H:i')
            : $lead->next_action_at->translatedFormat('D j M H:i');

        return [$lead->next_action_at->isPast() ? 'late' : ($lead->next_action_at->isToday() ? 'today' : 'later'), $wat.' '.$wanneer];
    };

    $initialen = fn (?string $naam) => $naam ? str($naam)->explode(' ')->filter()->take(2)->map(fn ($deel) => mb_strtoupper(mb_substr($deel, 0, 1)))->implode('') : null;
@endphp

<x-layouts.app title="Aanvragen">

    <x-page-header title="Aanvragen & sales" accent="sales"
                   subtitle="Sleep een kaart naar de volgende fase. Akkoord kan alleen via ondertekening van de offerte.">
        <a href="{{ route('leads.create') }}" class="btn pri"><x-icon name="plus" />Nieuwe aanvraag</a>
    </x-page-header>

    {{-- Vier cijfers; de gewogen pipeline is het getal waar het om draait --}}
    <section class="kpis even mb-[22px]">
        <div class="kpi" style="--kc: var(--h)"><span class="lbl">Open leads</span><span class="v" data-count>{{ $board['count'] }}</span></div>
        <div class="kpi" style="--kc: var(--h)"><span class="lbl">Potentiële omzet</span><span class="v" data-count>{{ $eur($board['value']) }}</span></div>
        <div class="kpi dark on-navy"><span class="lbl">Gewogen pipeline</span><span class="v"><b>€</b> <span data-count>{{ number_format($board['weighted'], 0, ',', '.') }}</span></span><span class="s">kans × waarde (10/25/40/60%)</span></div>
        <div class="kpi" style="--kc: var(--h)"><span class="lbl">Gewonnen dit jaar</span><span class="v" data-count>{{ $eur($board['won']) }}</span></div>
    </section>

    {{-- De verkooptrechter: aanvraag, intake, begroting, offerte --}}
    <div class="kb mb-[22px]"
         x-data="{
             dragging: null,
             async drop(status) {
                 if (! this.dragging) return;
                 await patchJson(this.dragging, { status });
                 window.location.reload();
             }
         }">
        @foreach ($board['columns'] as $index => $column)
            <div class="kcol" @dragover.prevent @drop="drop('{{ $column['status'] }}')">
                <h4>{{ $column['label'] }}<span class="pill mut">{{ $column['leads']->count() }}</span></h4>

                @foreach ($column['leads'] as $lead)
                    @php
                        $dossier = $lead->dossier;
                        [$toon, $tekst] = $stap($lead);
                        $waarde = (float) ($dossier->quote?->subtotal ?: $lead->value);
                        $url = $dossier->quote ? route('quotes.show', $dossier->quote) : route('leads.show', $lead);
                    @endphp
                    <div class="kcard" draggable="true" @dragstart="dragging = '{{ route('leads.status', $lead) }}'" @dragend="dragging = null">
                        <a href="{{ $url }}" class="grid gap-1.5">
                            <div class="flex items-center justify-between gap-2">
                                <b class="truncate">{{ $lead->customer->name }}</b>
                                <span class="pill or tabular-nums">{{ $column['chance'] }}%</span>
                            </div>
                            <span class="truncate text-muted">{{ collect([$lead->service, $lead->customer->city])->filter()->implode(', ') ?: 'Aanvraag' }}</span>
                            <div class="flex items-center justify-between gap-2">
                                <b class="tabular-nums">{{ $waarde > 0 ? $eur($waarde) : 'nog geen prijs' }}</b>
                                @if ($lead->assignee)
                                    <span class="av sm" title="{{ $lead->assignee->name }}">{{ $initialen($lead->assignee->name) }}</span>
                                @endif
                            </div>
                        </a>

                        @if ($lead->missingPhone())
                            <span class="pill bad justify-self-start">Telefoonnummer ontbreekt</span>
                        @endif

                        <div class="ct-card">
                            <span class="ct-row">
                                @if ($lead->last_contact_at)
                                    <span class="ct-l {{ $lead->missed_calls > 0 ? 'bad' : 'ok' }}">
                                        <x-icon name="phone" />{{ $lead->missed_calls > 0 ? 'Geen gehoor' : 'Contact gehad' }}
                                        @if ($lead->missed_calls > 1)<b>×{{ $lead->missed_calls }}</b>@endif
                                    </span>
                                    <small>{{ $lead->last_contact_at->isToday() ? 'vandaag' : $lead->last_contact_at->diffForHumans() }}</small>
                                @else
                                    <span class="ct-l bad"><x-icon name="exclamation-triangle" />Nog geen contact</span>
                                @endif
                            </span>
                            <span class="ct-n {{ $toon }}">{{ $tekst }}</span>
                            <a href="{{ route('leads.show', $lead) }}#contact" class="ct-log" title="Contactmoment vastleggen" aria-label="Contactmoment vastleggen voor {{ $lead->customer->name }}"><x-icon name="plus" /></a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    {{-- Opvolging: wie moet er gebeld worden, wie is niet bereikt, wie heeft geen vervolgstap --}}
    <section class="card ct-ov mb-[22px]" x-data="{ f: 'alle' }">
        <div class="card-h">
            <h3>Opvolging en contactmomenten</h3>
            <div class="ct-fs">
                @foreach ([
                    'alle' => ['Alles', $follow->count()],
                    'bellen' => ['Vandaag bellen', $follow->whereIn('state', ['late', 'today'])->count()],
                    'geen' => ['Niet bereikt', $follow->where('unreached', true)->count()],
                    'none' => ['Zonder vervolgstap', $follow->where('state', 'none')->count()],
                ] as $key => [$label, $aantal])
                    <button type="button" :class="f === '{{ $key }}' && 'on'" @click="f = '{{ $key }}'">{{ $label }}<span>{{ $aantal }}</span></button>
                @endforeach
            </div>
        </div>
        <div class="tw">
            <table class="t ct-t">
                <thead><tr><th>Aanvraag</th><th>Laatste contact</th><th>Pogingen</th><th>Volgende stap</th><th></th></tr></thead>
                <tbody>
                    @forelse ($follow as $row)
                        @php $lead = $row['lead']; @endphp
                        <tr x-show="f === 'alle' || (f === 'bellen' && {{ in_array($row['state'], ['late', 'today'], true) ? 'true' : 'false' }}) || (f === 'geen' && {{ $row['unreached'] ? 'true' : 'false' }}) || (f === 'none' && {{ $row['state'] === 'none' ? 'true' : 'false' }})">
                            <td>
                                <a class="ct-who" href="{{ route('leads.show', $lead) }}">
                                    <b>{{ $lead->customer->name }}</b>
                                    <small>{{ collect([$lead->service, $lead->dossier->stageLabel()])->filter()->implode(', ') }}</small>
                                </a>
                            </td>
                            <td>
                                @if ($lead->last_contact_at)
                                    <span class="ct-l {{ $lead->missed_calls > 0 ? 'bad' : 'ok' }}"><x-icon name="phone" />{{ $lead->missed_calls > 0 ? 'Geen gehoor' : 'Contact gehad' }}@if ($lead->missed_calls > 1) <b>×{{ $lead->missed_calls }}</b>@endif</span>
                                    <small class="ct-sub">{{ $lead->last_contact_at->translatedFormat('D j M H:i') }}@if ($lead->assignee), {{ str($lead->assignee->name)->before(' ') }}@endif</small>
                                @else
                                    <span class="ct-l bad"><x-icon name="exclamation-triangle" />Nog geen contact</span>
                                @endif
                            </td>
                            <td class="ct-cnt"><span><x-icon name="phone" />{{ (int) $lead->missed_calls }} gemist</span></td>
                            <td>
                                <span class="ct-n {{ $row['state'] }}">{{ $row['label'] }}</span>
                                @if ($lead->callAdvice())
                                    <small class="ct-sub">{{ $lead->callAdvice() }}</small>
                                @endif
                            </td>
                            <td class="r whitespace-nowrap">
                                @if ($lead->customer->phone)
                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $lead->customer->phone) }}" class="btn xs"><x-icon name="phone" />Bel</a>
                                @else
                                    <span class="pill bad">Geen nummer</span>
                                @endif
                                <a href="{{ route('leads.show', $lead) }}#contact" class="btn xs pri">Vastleggen</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-muted">Geen open aanvragen.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Wat we verloren, en waarom --}}
    @if ($inactief->isNotEmpty())
        <section class="card">
            <div class="card-h">
                <h3>Verloren <em>redenen</em></h3>
                <span class="pill mut">{{ $inactief->count() }}</span>
            </div>
            <div class="card-b" style="padding-block: 4px">
                @foreach ($inactief as $lead)
                    <a href="{{ route('leads.show', $lead) }}" class="li">
                        <span class="dotk {{ $lead->status === \App\Enums\LeadStatus::Verloren ? 'bad' : '' }}"></span>
                        <span class="tx">
                            <b class="truncate">{{ $lead->customer->name }}</b>
                            <small class="truncate">{{ collect([$lead->service, $lead->lost_reason])->filter()->implode(', ') ?: 'Geen reden vastgelegd' }}</small>
                        </span>
                        <x-status-badge :status="$lead->status" />
                    </a>
                @endforeach
            </div>
        </section>
    @endif

</x-layouts.app>
