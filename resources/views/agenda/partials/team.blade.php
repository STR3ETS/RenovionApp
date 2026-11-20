{{-- Team: medewerkers maal werkdagen. Zo zie je wie waar zit en waar nog ruimte is. --}}
@php
    $perVak = $items->groupBy(fn ($item) => ($item['user_id'] ?? 0).'|'.$item['date']);
    $zonderEigenaar = $items->whereNull('user_id');
@endphp

<div class="tw overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
    <table class="t" style="min-width: 900px">
        <thead>
            <tr>
                <th>Medewerker</th>
                @foreach ($days as $day)
                    <th class="{{ $day->isToday() ? 'text-brand-400!' : '' }}">{{ $day->translatedFormat('D j M') }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($team as $member)
                <tr>
                    <td class="align-top">
                        <a href="{{ route('agenda.index', ['weergave' => 'week', 'datum' => $date->toDateString(), 'medewerker' => $member->id]) }}"><b>{{ $member->name }}</b></a>
                        <span class="sub">{{ $member->role->label() }}</span>
                    </td>
                    @foreach ($days as $day)
                        <td class="cursor-pointer align-top transition hover:bg-white/[0.03]" style="min-width: 150px"
                            @click.self="nieuw(@js($day->toDateString()), '', @js($member->id))">
                            <div class="space-y-1">
                                @foreach ($perVak[$member->id.'|'.$day->toDateString()] ?? [] as $item)
                                    @include('agenda.partials.item', ['item' => $item])
                                @endforeach
                            </div>
                        </td>
                    @endforeach
                </tr>
            @endforeach

            @if ($zonderEigenaar->isNotEmpty())
                <tr>
                    <td class="align-top">
                        <b>Niet toegewezen</b>
                        <span class="sub">Aanvragen zonder eigenaar</span>
                    </td>
                    @foreach ($days as $day)
                        <td class="align-top" style="min-width: 150px">
                            <div class="space-y-1">
                                @foreach ($perVak['0|'.$day->toDateString()] ?? [] as $item)
                                    @include('agenda.partials.item', ['item' => $item])
                                @endforeach
                            </div>
                        </td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>
</div>
