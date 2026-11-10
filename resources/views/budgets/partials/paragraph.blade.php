@php
    $paragraafTotaal = $paragraph->total($rate);
@endphp

<div class="mb-3 overflow-hidden rounded-xl border border-surface-700 bg-surface-800">
    <div class="flex items-center justify-between gap-3 border-b border-surface-700 px-3 py-2.5">
        <p class="min-w-0 truncate text-xs font-bold tracking-wide text-slate-300 uppercase">
            {{ $chapter->code }}{{ $paragraph->code }} {{ $paragraph->name }}
        </p>
        <p class="shrink-0 text-sm font-bold text-white">EUR {{ number_format($paragraafTotaal, 0, ',', '.') }}</p>
    </div>

    @if ($paragraph->lines->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-surface-700 text-[10px] font-bold tracking-wider text-slate-500 uppercase">
                        <th class="px-3 py-2 text-left">Omschrijving</th>
                        <th class="px-2 py-2 text-right">Aantal</th>
                        <th class="px-2 py-2 text-left">Eenheid</th>
                        <th class="px-2 py-2 text-right" title="Normuren per eenheid">Uren</th>
                        <th class="px-2 py-2 text-right" title="Materiaal per eenheid">Mat.</th>
                        <th class="px-2 py-2 text-right" title="Materieel per eenheid">Mtr.</th>
                        <th class="px-2 py-2 text-right" title="Onderaanneming per eenheid">Ond.</th>
                        <th class="px-2 py-2 text-right" title="Overige kosten per eenheid">Ov.</th>
                        <th class="px-3 py-2 text-right">Totaal</th>
                        @if ($budget->isDraft())
                            <th class="w-8"></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($paragraph->lines as $line)
                        @php($kosten = $line->costs($rate))
                        <tr class="border-b border-surface-700/60 last:border-b-0 {{ $line->isIncomplete() ? 'bg-amber-500/5' : '' }}">
                            @if ($line->type === \App\Models\BudgetLine::TYPE_TEXT)
                                <td colspan="{{ $budget->isDraft() ? 10 : 9 }}" class="px-3 py-2 text-xs text-slate-400 italic">
                                    {{ $line->description }}
                                </td>
                            @elseif ($budget->isDraft())
                                {{-- Inline bewerken: één formulier per regel, opslaan op blur --}}
                                <form method="POST" action="{{ route('budget-lines.update', $line) }}" id="line-{{ $line->id }}" class="contents">
                                    @csrf
                                    @method('PATCH')
                                    <td class="px-3 py-1.5">
                                        <input type="text" name="description" value="{{ $line->description }}" onchange="this.form.requestSubmit()"
                                               class="w-full min-w-48 rounded-lg border-0 bg-transparent px-1 py-1 text-sm text-white focus:bg-surface-850 focus:ring-1 focus:ring-brand-500">
                                        @if ($line->source)
                                            <span class="block px-1 text-[10px] text-slate-500">{{ $line->source }}</span>
                                        @endif
                                    </td>
                                    <td class="px-1 py-1.5">
                                        <input type="text" name="quantity" value="{{ rtrim(rtrim(number_format((float) $line->quantity, 3, ',', '.'), '0'), ',') }}" onchange="this.form.requestSubmit()"
                                               class="w-16 rounded-lg border-0 bg-transparent px-1 py-1 text-right text-sm text-white focus:bg-surface-850 focus:ring-1 focus:ring-brand-500">
                                    </td>
                                    <td class="px-1 py-1.5">
                                        <input type="text" name="unit" value="{{ $line->unit }}" onchange="this.form.requestSubmit()"
                                               class="w-12 rounded-lg border-0 bg-transparent px-1 py-1 text-sm text-slate-400 focus:bg-surface-850 focus:ring-1 focus:ring-brand-500">
                                    </td>
                                    @foreach (['norm_hours' => 4, 'material' => 2, 'equipment' => 2, 'subcontracting' => 2, 'other' => 2] as $field => $decimals)
                                        <td class="px-1 py-1.5">
                                            <input type="text" name="{{ $field }}"
                                                   value="{{ (float) $line->{$field} === 0.0 ? '' : rtrim(rtrim(number_format((float) $line->{$field}, $decimals, ',', '.'), '0'), ',') }}"
                                                   onchange="this.form.requestSubmit()" placeholder="0"
                                                   class="w-16 rounded-lg border-0 bg-transparent px-1 py-1 text-right text-sm text-white placeholder:text-slate-600 focus:bg-surface-850 focus:ring-1 focus:ring-brand-500">
                                        </td>
                                    @endforeach
                                    <td class="px-3 py-1.5 text-right font-semibold text-white">
                                        {{ number_format($kosten['totaal'], 0, ',', '.') }}
                                    </td>
                                </form>
                                <td class="px-1 py-1.5">
                                    <form method="POST" action="{{ route('budget-lines.destroy', $line) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="Regel verwijderen" class="text-slate-600 transition hover:text-red-400">
                                            <x-icon name="trash" class="h-3.5 w-3.5" />
                                        </button>
                                    </form>
                                </td>
                            @else
                                <td class="px-3 py-2 text-slate-200">
                                    {{ $line->description }}
                                    @if ($line->source)
                                        <span class="block text-[10px] text-slate-500">{{ $line->source }}</span>
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-right text-slate-300">{{ rtrim(rtrim(number_format((float) $line->quantity, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="px-2 py-2 text-slate-500">{{ $line->unit }}</td>
                                <td class="px-2 py-2 text-right text-slate-400">{{ $kosten['uren'] > 0 ? rtrim(rtrim(number_format($kosten['uren'], 2, ',', '.'), '0'), ',') : '' }}</td>
                                <td class="px-2 py-2 text-right text-slate-400">{{ $kosten['materiaal'] > 0 ? number_format($kosten['materiaal'], 0, ',', '.') : '' }}</td>
                                <td class="px-2 py-2 text-right text-slate-400">{{ $kosten['materieel'] > 0 ? number_format($kosten['materieel'], 0, ',', '.') : '' }}</td>
                                <td class="px-2 py-2 text-right text-slate-400">{{ $kosten['onderaanneming'] > 0 ? number_format($kosten['onderaanneming'], 0, ',', '.') : '' }}</td>
                                <td class="px-2 py-2 text-right text-slate-400">{{ $kosten['overig'] > 0 ? number_format($kosten['overig'], 0, ',', '.') : '' }}</td>
                                <td class="px-3 py-2 text-right font-semibold text-white">{{ number_format($kosten['totaal'], 0, ',', '.') }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($budget->isDraft())
        @include('budgets.partials.line-form', ['paragraph' => $paragraph])
    @endif
</div>
