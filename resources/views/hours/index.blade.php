@php
    use App\Models\Project;
    $eur = fn (float $v) => '€ '.number_format($v, 0, ',', '.');
    $num = fn (float $v) => rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
@endphp

<x-layouts.app title="Urenstaten">
    <x-page-header title="Urenstaten" category="offerte"
                   subtitle="Alle urenstaten op een rij. Regie rekenen we af zodra de uren zijn afgetekend, bij aangenomen werk staan ze er ter informatie." />

    <x-kpi-row>
        <x-kpi dark label="Regie nog te factureren" :value="$eur($toInvoice)" sub="exclusief btw, over alle projecten" />
        <x-kpi label="Wacht op aftekenen" :value="(string) $unsigned" :alert="$unsigned > 0"
               :sub="$unsigned > 0 ? 'niet of niet meer afgetekend' : 'alles afgetekend'" />
        <x-kpi label="Uren geboekt" :value="$num($hours)" sub="op de getoonde projecten" />
        <x-kpi label="Projecten" :value="(string) $rows->count()" sub="met een urenstaat" />
    </x-kpi-row>

    <x-tabs>
        <x-tab :href="route('hours.index')" :active="$form === ''" :count="$counts->sum()">Alle</x-tab>
        @foreach (Project::FORMS as $key => $label)
            <x-tab :href="route('hours.index', ['vorm' => $key])" :active="$form === $key" :count="$counts[$key] ?? 0">{{ $label }}</x-tab>
        @endforeach
    </x-tabs>

    @if ($rows->isEmpty())
        <x-empty-state icon="clock" category="offerte" title="Nog geen urenstaten"
                       subtitle="Uren komen vanzelf binnen uit goedgekeurde werkbonnen, of je boekt ze op het tabblad Urenstaat van een project." />
    @else
        <div class="tw overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
            <table class="t">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Vorm</th>
                        <th class="r">Uren</th>
                        <th>Afgetekend</th>
                        <th class="r">Totaal</th>
                        <th class="r">Nog te factureren</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php
                            $project = $row['project'];
                            $statement = $row['statement'];
                        @endphp
                        <tr class="click" onclick="window.location='{{ route('projects.show', [$project, 'tab' => 'uren']) }}'">
                            <td>
                                <a href="{{ route('projects.show', [$project, 'tab' => 'uren']) }}"><b>{{ $project->name }}</b></a>
                                <span class="sub">{{ $project->number() }}, {{ $project->customer?->name }}</span>
                            </td>
                            <td>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $statement->isRegie() ? 'bg-planning-500/15 text-planning-300' : 'bg-ink/10 text-ink2' }}">
                                    {{ Project::FORMS[$project->contract_form] ?? $project->contract_form }}
                                </span>
                            </td>
                            <td class="r">{{ $num($statement->hours()) }}</td>
                            <td>
                                @if (! $statement->isSigned())
                                    <span class="text-amber-300">Nog niet</span>
                                @elseif ($statement->needsResign())
                                    <span class="text-amber-300">Opnieuw nodig</span>
                                    <span class="sub">uren zijn veranderd</span>
                                @else
                                    <span class="text-green-300">{{ $project->hoursSigner?->name ?? 'Ja' }}</span>
                                    <span class="sub">{{ $project->hours_signed_at->translatedFormat('j M Y') }}</span>
                                @endif
                            </td>
                            <td class="r">{{ $statement->isRegie() ? $eur($statement->total()) : 'vaste prijs' }}</td>
                            <td class="r">
                                @if ($statement->isRegie())
                                    <b>{{ $eur($statement->toInvoice()) }}</b>
                                @else
                                    <span class="text-faint">ter informatie</span>
                                @endif
                            </td>
                            <td class="r">
                                <a href="{{ route('hours.print', $project) }}" target="_blank" rel="noopener" onclick="event.stopPropagation()"
                                   class="btn sm">Uitdraaien</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.app>
