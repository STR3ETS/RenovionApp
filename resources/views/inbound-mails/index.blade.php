@php
    use App\Enums\CustomerType;
    use App\Models\InboundMail;
    $voorgesteld = $selected?->customer_id ?? ($selected?->extracted['klant_id'] ?? null);
@endphp

<x-layouts.app title="Aanvraagmail">
    <div x-data="{ nieuw: {{ $errors->has('body') ? 'true' : 'false' }} }">
        <x-page-header title="Aanvraagmail" category="lead"
                       subtitle="Wat binnenkomt op aanvraag@ en via de opdrachtlink van zakelijke klanten. Nova leest wat er gevraagd wordt, jij bevestigt, koppelt en zet het uit.">
            <button type="button" @click="nieuw = !nieuw"
                    class="btn pri">
                <x-icon name="plus" class="h-4 w-4" /> Bericht invoeren
            </button>
        </x-page-header>

        {{-- Zolang de mailbox niet gekoppeld is, plak je een mail er zelf in --}}
        <form x-show="nieuw" x-cloak method="POST" action="{{ route('inbound-mails.store') }}"
              class="mb-4 grid gap-3 rounded-2xl border border-surface-700 bg-surface-800 p-5 sm:grid-cols-2">
            @csrf
            <label class="block text-xs text-muted">Van, naam
                <input type="text" name="from_name" value="{{ old('from_name') }}" maxlength="255" class="mt-1 w-full rounded-xl border-surface-600 text-sm">
            </label>
            <label class="block text-xs text-muted">Van, e-mailadres
                <input type="email" name="from_email" value="{{ old('from_email') }}" maxlength="255" class="mt-1 w-full rounded-xl border-surface-600 text-sm">
            </label>
            <label class="block text-xs text-muted sm:col-span-2">Onderwerp
                <input type="text" name="subject" value="{{ old('subject') }}" maxlength="255" class="mt-1 w-full rounded-xl border-surface-600 text-sm">
            </label>
            <label class="block text-xs text-muted sm:col-span-2">Het bericht
                <textarea name="body" rows="5" required maxlength="20000" class="mt-1 w-full rounded-xl border-surface-600 text-sm">{{ old('body') }}</textarea>
            </label>
            @error('body')<p class="text-xs font-medium text-red-400 sm:col-span-2">{{ $message }}</p>@enderror
            <div class="flex justify-end sm:col-span-2">
                <button type="submit" class="btn pri">Toevoegen en laten lezen</button>
            </div>
        </form>
    </div>

    <x-tabs>
        <x-tab :href="route('inbound-mails.index')" :active="$status === ''"
               :count="($counts[InboundMail::STATUS_NEW] ?? 0) + ($counts[InboundMail::STATUS_LINKED] ?? 0)">Te doen</x-tab>
        @foreach (InboundMail::STATUSES as $key => $label)
            <x-tab :href="route('inbound-mails.index', ['status' => $key])" :active="$status === $key" :count="$counts[$key] ?? 0">{{ $label }}</x-tab>
        @endforeach
    </x-tabs>

    @if ($mails->isEmpty() && $selected === null)
        <x-empty-state icon="envelope" category="lead" title="Niets te doen in de aanvraagmail"
                       subtitle="Nieuwe berichten verschijnen hier zodra ze binnenkomen. Je kunt er ook zelf een invoeren." />
    @else
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
            {{-- De lijst --}}
            <div class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
                @forelse ($mails as $mail)
                    <a href="{{ route('inbound-mails.index', array_filter(['status' => $status ?: null, 'bericht' => $mail->id])) }}"
                       class="block border-b border-surface-700 border-l-4 p-4 transition last:border-b-0 hover:bg-ink/5 {{ $selected?->id === $mail->id ? 'border-l-brand-500 bg-white/[0.04]' : 'border-l-transparent' }}">
                        <span class="flex flex-wrap items-center gap-2">
                            <b class="min-w-0 truncate text-sm font-semibold text-ink">{{ $mail->from_name ?: ($mail->from_email ?: 'Onbekende afzender') }}</b>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $mail->badgeClasses() }}">{{ $mail->statusLabel() }}</span>
                            @if ($mail->fromPortal())
                                <span class="rounded-full bg-offerte-500/15 px-2 py-0.5 text-[10px] font-bold text-offerte-300">Opdrachtlink</span>
                            @endif
                        </span>
                        <span class="mt-0.5 block truncate text-sm text-ink2">{{ $mail->read('werk') ?? $mail->subject ?? 'Geen onderwerp' }}</span>
                        <span class="mt-0.5 block truncate text-xs text-faint">
                            {{ $mail->received_at?->diffForHumans() }}{{ $mail->customer ? ', '.$mail->customer->name : '' }}
                        </span>
                    </a>
                @empty
                    <p class="p-5 text-sm text-faint">Geen berichten met deze status.</p>
                @endforelse
            </div>

            {{-- Het gekozen bericht --}}
            @if ($selected)
                <div class="space-y-4">
                    <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="text-base font-bold text-ink">{{ $selected->subject ?: 'Geen onderwerp' }}</h2>
                                <p class="mt-0.5 text-xs text-muted">
                                    {{ collect([$selected->from_name, $selected->from_email])->filter()->join(', ') ?: 'Onbekende afzender' }},
                                    {{ $selected->received_at?->translatedFormat('j F Y H:i') }}
                                </p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $selected->badgeClasses() }}">{{ $selected->statusLabel() }}</span>
                        </div>
                        <p class="mt-4 max-h-72 overflow-y-auto rounded-xl bg-surface-850 p-4 text-sm whitespace-pre-line text-ink">{{ $selected->body }}</p>
                    </section>

                    @if ($selected->status === InboundMail::STATUS_DISPATCHED)
                        <section class="flex flex-wrap items-center gap-3 rounded-2xl border border-green-500/25 bg-green-500/10 p-5">
                            <x-icon name="check" class="h-6 w-6 shrink-0 text-green-300" />
                            <p class="min-w-0 flex-1 text-sm text-ink">
                                Uitgezet als aanvraag voor {{ $selected->customer?->name }}{{ $selected->handler ? ', door '.$selected->handler->name : '' }}.
                            </p>
                            @if ($selected->lead)
                                <a href="{{ route('leads.show', $selected->lead) }}" class="btn">Naar de aanvraag</a>
                            @endif
                        </section>
                    @else
                        {{-- Wat Nova las: altijd een voorstel dat een mens bevestigt --}}
                        <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <h2 class="flex items-center gap-2 text-sm font-bold text-ink">
                                    <x-nova-avatar class="h-6 w-6" /> Nova las <span class="text-brand-500">dit</span>
                                </h2>
                                <form method="POST" action="{{ route('inbound-mails.reread', $selected) }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-muted hover:text-ink">Opnieuw lezen</button>
                                </form>
                            </div>
                            <p class="mb-4 text-xs text-faint">
                                Controleer het en pas aan wat niet klopt. Er gebeurt niets tot je koppelt.
                                @if (($selected->extracted['bron'] ?? null) === 'patronen')
                                    Nova is niet gekoppeld, dus dit komt uit vaste patronen en is minder trefzeker.
                                @endif
                            </p>

                            <form method="POST" action="{{ route('inbound-mails.link', $selected) }}" class="space-y-3"
                                  x-data="{ klant: @js($voorgesteld ? (string) $voorgesteld : (old('new_name') ? 'nieuw' : '')) }">
                                @csrf
                                <label class="block text-xs text-muted">Gevraagd werk
                                    <input type="text" name="werk" value="{{ old('werk', $selected->read('werk')) }}" required maxlength="255" class="mt-1 w-full rounded-xl border-surface-600 text-sm">
                                </label>
                                <div class="grid gap-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                                    <label class="block text-xs text-muted">Adres van het werk
                                        <input type="text" name="adres" value="{{ old('adres', $selected->read('adres')) }}" maxlength="255" class="mt-1 w-full rounded-xl border-surface-600 text-sm">
                                    </label>
                                    <label class="block text-xs text-muted">Deadline
                                        <input type="date" name="deadline" value="{{ old('deadline', $selected->read('deadline')) }}" class="mt-1 w-full rounded-xl border-surface-600 text-sm">
                                    </label>
                                </div>

                                <label class="block text-xs text-muted">Klant
                                    <select x-model="klant" class="mt-1 w-full rounded-xl border-surface-600 text-sm" aria-label="Klant">
                                        <option value="">Kies een klant</option>
                                        <option value="nieuw">Nieuwe klant aanmaken</option>
                                        @foreach ($customers as $customer)
                                            <option value="{{ $customer->id }}">{{ $customer->name }}, {{ $customer->number }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <input type="hidden" name="customer_id" :value="klant !== 'nieuw' ? klant : ''">

                                @if ($selected->read('klant_reden'))
                                    <p class="text-xs text-green-300">Voorstel van Nova: {{ $selected->read('klant_reden') }}.</p>
                                @elseif (! $voorgesteld)
                                    <p class="text-xs text-faint">Nova herkende geen bestaande klant in dit bericht.</p>
                                @endif

                                <div x-show="klant === 'nieuw'" x-cloak class="grid gap-3 rounded-xl bg-surface-850 p-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                                    <label class="block text-xs text-muted">Naam van de nieuwe klant
                                        <input type="text" name="new_name" value="{{ old('new_name', $selected->from_name) }}" maxlength="255" :disabled="klant !== 'nieuw'"
                                               class="mt-1 w-full rounded-xl border-surface-600 text-sm">
                                    </label>
                                    <label class="block text-xs text-muted">Soort
                                        <select name="new_type" :disabled="klant !== 'nieuw'" class="mt-1 w-full rounded-xl border-surface-600 text-sm">
                                            @foreach (CustomerType::cases() as $case)
                                                <option value="{{ $case->value }}">{{ $case->label() }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                </div>
                                @error('customer_id')<p class="text-xs font-medium text-red-400">{{ $message }}</p>@enderror

                                <button type="submit" class="w-full rounded-full border border-surface-600 py-2.5 text-sm font-semibold text-ink transition hover:border-brand-500 hover:text-ink">
                                    {{ $selected->status === InboundMail::STATUS_LINKED ? 'Koppeling bijwerken' : 'Bevestigen en koppelen' }}
                                </button>
                            </form>
                        </section>

                        @if ($selected->status === InboundMail::STATUS_LINKED)
                            <section class="rounded-2xl border border-brand-500/30 bg-brand-500/5 p-5">
                                <h2 class="text-sm font-bold text-ink">Uit<span class="text-brand-500">zetten</span></h2>
                                <p class="mt-1 text-sm text-ink2">
                                    Gekoppeld aan <b class="text-ink">{{ $selected->customer?->name }}</b>.
                                    @if ($selected->isForExistingCustomer())
                                        Dit is een bestaande klant: het dossier gaat direct naar de calculatie, met een taak Offerte maken.
                                        @if ($selected->customer?->activeFramework())
                                            De vaste prijzen uit de raamovereenkomst staan klaar in een nieuwe begroting.
                                        @endif
                                    @else
                                        Dit is een nieuwe klant: het dossier begint bij de aanvraag.
                                    @endif
                                </p>
                                <form method="POST" action="{{ route('inbound-mails.dispatch', $selected) }}" class="mt-3">
                                    @csrf
                                    <button type="submit" class="btn pri lg w-full">Uitzetten als aanvraag</button>
                                </form>
                            </section>
                        @endif
                    @endif
                </div>
            @endif
        </div>
    @endif
</x-layouts.app>
