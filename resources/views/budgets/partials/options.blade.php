@php
    use App\Models\BudgetOption;
@endphp

{{-- Opties staan buiten de aanneemsom tot de klant akkoord geeft. Vanaf dat
     moment tellen ze als meerwerk mee, met dezelfde staart. --}}
<div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
    <h2 class="mb-1 text-sm font-bold text-white">Opties <span class="text-brand-500">en meerwerk</span></h2>
    <p class="mb-4 text-xs text-slate-500">
        @if ($totals->approvedExtras > 0)
            EUR {{ number_format($totals->approvedExtras, 0, ',', '.') }} akkoord, EUR {{ number_format($totals->openOptions, 0, ',', '.') }} nog open.
        @else
            EUR {{ number_format($totals->openOptions, 0, ',', '.') }} open, nog niets akkoord.
        @endif
    </p>

    @if ($budget->options->isEmpty())
        <p class="text-sm text-slate-500">Geen opties. Alles wat de klant kan kiezen, zet je hier apart.</p>
    @else
        <div class="space-y-2">
            @foreach ($budget->options as $option)
                @php($bedrag = (float) $option->quantity * (float) $option->price * $totals->markupFactor)
                <div class="rounded-xl border border-surface-700 bg-surface-850 p-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-white">{{ $option->description }}</p>
                            @if ($option->note)
                                <p class="mt-0.5 text-xs text-slate-400">{{ $option->note }}</p>
                            @endif
                            <p class="mt-1 text-xs text-slate-500">
                                {{ rtrim(rtrim(number_format((float) $option->quantity, 2, ',', '.'), '0'), ',') }} {{ $option->unit }}
                                @if ($option->needsPricing())
                                    <span class="font-semibold text-amber-300">, prijs nog invullen</span>
                                @else
                                    , EUR {{ number_format($bedrag, 0, ',', '.') }} inclusief staart
                                @endif
                            </p>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $option->badgeClasses() }}">{{ $option->statusLabel() }}</span>
                    </div>

                    @if ($budget->isDraft())
                        <div class="mt-2 flex flex-wrap items-center gap-2 border-t border-surface-700 pt-2">
                            <form method="POST" action="{{ route('budget-options.update', $option) }}" class="flex items-center gap-1.5">
                                @csrf
                                @method('PATCH')
                                <input type="text" name="price" value="{{ (float) $option->price === 0.0 ? '' : number_format((float) $option->price, 2, ',', '.') }}"
                                       placeholder="Prijs" onchange="this.form.requestSubmit()"
                                       class="w-20 rounded-lg border-surface-600 bg-surface-800 py-1.5 text-right text-xs text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
                            </form>

                            @foreach ([[BudgetOption::STATUS_APPROVED, 'Akkoord'], [BudgetOption::STATUS_OPEN, 'Open'], [BudgetOption::STATUS_DECLINED, 'Afwijzen']] as [$waarde, $label])
                                @continue($option->status === $waarde)
                                <form method="POST" action="{{ route('budget-options.update', $option) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $waarde }}">
                                    <button type="submit" class="rounded-lg border border-surface-600 px-2.5 py-1.5 text-xs font-semibold text-slate-300 transition hover:text-white">{{ $label }}</button>
                                </form>
                            @endforeach

                            <form method="POST" action="{{ route('budget-options.destroy', $option) }}" class="ml-auto">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Verwijderen" class="text-slate-600 transition hover:text-red-400">
                                    <x-icon name="trash" class="h-3.5 w-3.5" />
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if ($budget->isDraft())
        <form method="POST" action="{{ route('budget-options.store', $budget) }}" class="mt-3 space-y-2">
            @csrf
            <input type="text" name="description" required placeholder="Nieuwe optie"
                   class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
            <div class="flex gap-2">
                <input type="text" name="quantity" value="1" title="Aantal"
                       class="w-16 shrink-0 rounded-xl border-surface-600 bg-surface-850 py-2 text-right text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                <input type="text" name="unit" value="pst" title="Eenheid"
                       class="w-16 shrink-0 rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                <input type="text" name="price" placeholder="Prijs per eenheid"
                       class="min-w-0 flex-1 rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
                <button type="submit" class="shrink-0 rounded-xl border border-surface-600 px-3 py-2 text-slate-300 transition hover:text-white">
                    <x-icon name="plus" class="h-4 w-4" />
                </button>
            </div>
        </form>
    @endif
</div>
