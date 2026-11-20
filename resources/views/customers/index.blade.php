@php
    use App\Enums\CustomerType;
    $eur = fn (float $v) => '€ '.number_format($v, 0, ',', '.');
@endphp

<x-layouts.app title="Klanten">

    <x-page-header title="Klanten & CRM" accent="CRM" :subtitle="$counts->sum().' relaties. Particulieren laten een klus doen, zakelijke klanten zijn de partijen voor wie we werken.'">
        <form method="GET" action="{{ route('customers.index') }}">
            @if ($type)
                <input type="hidden" name="soort" value="{{ $type->value }}">
            @endif
            <input type="search" name="q" value="{{ $search }}" placeholder="Zoek op naam, klantnummer of plaats"
                   class="w-64 rounded-full border-surface-700 bg-surface-850 py-2.5 text-sm text-ink placeholder:text-faint focus:border-brand-500 focus:ring-brand-500">
        </form>
        <a href="{{ route('leads.create') }}" class="btn pri"><x-icon name="plus" />Klant toevoegen</a>
    </x-page-header>

    {{-- Hoeveel relaties, en hoe ze verdeeld zijn --}}
    <section class="kpis mb-[22px]" style="grid-template-columns: repeat(3, minmax(0, 1fr))">
        <div class="kpi"><span class="lbl">Relaties</span><span class="v" data-count>{{ $counts->sum() }}</span></div>
        @foreach (CustomerType::cases() as $case)
            <div class="kpi"><span class="lbl">{{ $case->label() }}</span><span class="v" data-count>{{ $counts[$case->value] ?? 0 }}</span></div>
        @endforeach
    </section>

    <x-tabs>
        <x-tab :href="route('customers.index', ['q' => $search ?: null])" :active="$type === null" :count="$counts->sum()">Alle</x-tab>
        @foreach (CustomerType::cases() as $case)
            <x-tab :href="route('customers.index', ['soort' => $case->value, 'q' => $search ?: null])"
                   :active="$type === $case" :count="$counts[$case->value] ?? 0">{{ $case->label() }}</x-tab>
        @endforeach
    </x-tabs>

    @if ($customers->isEmpty())
        <x-empty-state title="Geen klanten gevonden" icon="users" category="klant"
                       subtitle="Een klant ontstaat vanzelf bij de eerste aanvraag." />
    @else
        <div class="card tw overflow-hidden">
            <table class="t">
                <thead>
                    <tr>
                        <th>Klant</th>
                        <th>Klantnr.</th>
                        <th>Soort</th>
                        <th>Contact</th>
                        <th>Plaats</th>
                        <th>Betalen</th>
                        <th>Dossiers</th>
                        <th class="r">Totale waarde</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($customers as $customer)
                        @php($framework = $customer->activeFramework())
                        <tr class="click" onclick="window.location='{{ route('customers.show', $customer) }}'">
                            <td>
                                <a href="{{ route('customers.show', $customer) }}"><b>{{ $customer->name }}</b></a>
                            </td>
                            <td class="tabular-nums">{{ $customer->number }}</td>
                            <td>
                                <span class="pill {{ $customer->type->badgeClasses() }}">{{ $customer->type->label() }}</span>
                                @if ($framework)
                                    <span class="pill or ml-1">Raamovereenkomst</span>
                                @endif
                            </td>
                            <td>
                                {{ $customer->contact_name ?: ($customer->phone ?? 'geen telefoon') }}
                                @if ($customer->contact_name && $customer->phone)
                                    <span class="sub">{{ $customer->phone }}</span>
                                @endif
                            </td>
                            <td>{{ $customer->city ?? 'onbekend' }}</td>
                            <td>
                                {{ $customer->payment_days }} dagen
                                <span class="sub">{{ $customer->reminders ? 'met herinnering' : 'geen herinnering' }}</span>
                            </td>
                            <td>
                                {{ $customer->leads_count }} {{ $customer->leads_count === 1 ? 'aanvraag' : 'aanvragen' }},
                                {{ $customer->projects_count }} {{ $customer->projects_count === 1 ? 'project' : 'projecten' }}
                            </td>
                            <td class="r">{{ $eur((float) $customer->projects_sum_value) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $customers->links() }}
        </div>
    @endif

</x-layouts.app>
