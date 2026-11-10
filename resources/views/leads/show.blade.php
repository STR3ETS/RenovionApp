<x-layouts.app :title="$lead->customer->name">

    <x-page-header eyebrow="Aanvraag" category="lead" :title="$lead->customer->name" :subtitle="($lead->service ?? 'Aanvraag').', '.($lead->customer->city ?? 'plaats onbekend')">
        <x-status-badge :status="$lead->status" class="text-sm" />
        <a href="{{ route('leads.edit', $lead) }}" class="rounded-lg border border-surface-600 bg-surface-800 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:bg-surface-850">Bewerken</a>
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">

            {{-- Signalen --}}
            @php
                $signalen = collect([
                    $lead->missingPhone() ? ['color' => 'amber', 'text' => 'Geen telefoonnummer bekend'] : null,
                    $lead->next_action_at?->isPast() ? ['color' => 'red', 'text' => 'Opvolging verlopen: '.($lead->next_action ?? 'actie').' ('.$lead->next_action_at->translatedFormat('j M H:i').')'] : null,
                    $lead->isSilent() ? ['color' => 'amber', 'text' => 'Geen volgende actie gepland en al '.((int) ($lead->last_contact_at ?? $lead->created_at)->diffInDays()).' dagen geen contact'] : null,
                    $lead->status->isOpen() && $lead->qualification === \App\Enums\LeadQualification::Onbeoordeeld ? ['color' => 'gray', 'text' => 'Nog niet gekwalificeerd - beoordeel de aanvraag (koud/warm/heet)'] : null,
                ])->filter();
            @endphp
            @if ($signalen->isNotEmpty())
                <div class="space-y-1 rounded-xl border border-amber-300 bg-amber-500/10 p-4">
                    @foreach ($signalen as $signaal)
                        <p class="flex items-center gap-2 text-sm font-medium text-amber-300"><x-signal-dot :color="$signaal['color']" /> {{ $signaal['text'] }}</p>
                    @endforeach
                </div>
            @endif

            {{-- Snelle acties --}}
            @php $quotesEnabled = config('renovion.modules.quotes'); @endphp
            <div class="grid grid-cols-2 gap-2 {{ $quotesEnabled ? 'sm:grid-cols-5' : 'sm:grid-cols-4' }}">
                @if ($lead->customer->phone)
                    <a href="tel:{{ $lead->customer->phone }}" class="flex items-center justify-center gap-1.5 rounded-xl bg-brand-600 px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-500"><x-icon name="phone" /> Bellen</a>
                @endif
                @if ($lead->customer->email)
                    <a href="mailto:{{ $lead->customer->email }}" class="flex items-center justify-center gap-1.5 rounded-xl border border-surface-600 bg-surface-800 px-3 py-2.5 text-sm font-semibold text-white transition hover:border-brand-400"><x-icon name="envelope" /> Mailen</a>
                @endif
                <a href="{{ route('calculations.create', ['aanvraag' => $lead->id]) }}" class="flex items-center justify-center gap-1.5 rounded-xl bg-navy-900 px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800"><x-icon name="calculator" /> Calculatie</a>
                @if ($quotesEnabled)
                    <a href="{{ route('quotes.create', ['aanvraag' => $lead->id]) }}" class="flex items-center justify-center gap-1.5 rounded-xl bg-navy-900 px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800"><x-icon name="document-text" /> Offerte maken</a>
                @endif
                <a href="{{ route('customers.show', $lead->customer) }}" class="flex items-center justify-center gap-1.5 rounded-xl border border-surface-600 bg-surface-800 px-3 py-2.5 text-sm font-semibold text-white transition hover:border-brand-400"><x-icon name="folder" /> Klantdossier</a>
            </div>

            {{-- Contactmoment vastleggen (briefing §4: Nova bewaakt contactmomenten) --}}
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-3 text-sm font-bold text-white">Contactmoment vastleggen</h2>
                <form method="POST" action="{{ route('leads.contacts.store', $lead) }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        @foreach ([
                            'telefoon' => ['label' => 'Telefoon', 'icon' => 'phone'],
                            'email' => ['label' => 'E-mail', 'icon' => 'envelope'],
                            'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'chat-bubble'],
                            'bezoek' => ['label' => 'Bezoek', 'icon' => 'calendar'],
                        ] as $typeValue => $typeOption)
                            <label class="flex cursor-pointer items-center justify-center gap-1.5 rounded-xl border border-surface-700 px-3 py-2.5 text-sm font-semibold text-slate-300 transition has-checked:border-brand-400 has-checked:bg-brand-500/10 has-checked:text-brand-300">
                                <input type="radio" name="type" value="{{ $typeValue }}" @checked(old('type', 'telefoon') === $typeValue) class="sr-only">
                                <x-icon :name="$typeOption['icon']" class="h-4 w-4" /> {{ $typeOption['label'] }}
                            </label>
                        @endforeach
                    </div>
                    @error('type')<p class="text-xs font-medium text-red-400">{{ $message }}</p>@enderror

                    <x-field label="Wat is er besproken?" name="summary">
                        <textarea name="summary" id="summary" rows="2" required placeholder="Bijv. gebeld: wil eerst de badkamer, budget rond 15k, stuurt foto's door"
                                  class="w-full rounded-xl border-surface-600 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('summary') }}</textarea>
                    </x-field>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field label="Volgende actie" name="next_action">
                            <input type="text" name="next_action" id="next_action" value="{{ old('next_action') }}" placeholder="Bijv. terugbellen na foto's"
                                   class="w-full rounded-xl border-surface-600 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </x-field>
                        <x-field label="Gepland op" name="next_action_at">
                            <input type="datetime-local" name="next_action_at" id="next_action_at" value="{{ old('next_action_at') }}"
                                   class="w-full rounded-xl border-surface-600 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </x-field>
                    </div>
                    <p class="text-xs text-slate-500">Zonder volgende actie meldt Nova deze aanvraag na 3 dagen stilte in Aandacht.</p>

                    <button type="submit" class="rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">Vastleggen</button>
                </form>
            </section>

            {{-- Status wijzigen --}}
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-3 text-sm font-bold text-white">Status</h2>
                <form method="POST" action="{{ route('leads.status', $lead) }}" class="flex flex-wrap items-center gap-2">
                    @csrf
                    @method('PATCH')
                    @foreach (\App\Enums\LeadStatus::cases() as $statusOption)
                        <button type="submit" name="status" value="{{ $statusOption->value }}"
                                @disabled($statusOption === $lead->status)
                                class="rounded-full px-3 py-1.5 text-xs font-semibold transition {{ $statusOption === $lead->status ? 'cursor-default '.$statusOption->badgeClasses().' ring-2 ring-navy-900/20' : 'bg-surface-750 text-slate-300 hover:bg-surface-700' }}">
                            {{ $statusOption->label() }}
                        </button>
                    @endforeach
                </form>
            </section>

            {{-- Aanvraag --}}
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-3 text-sm font-bold text-white">Aanvraag</h2>
                <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    <div><dt class="text-slate-400">Dienst</dt><dd class="font-medium">{{ $lead->service ?? '-' }}</dd></div>
                    <div><dt class="text-slate-400">Waarde</dt><dd class="font-medium">{{ $lead->value ? '€ '.number_format((float) $lead->value, 0, ',', '.') : '-' }}</dd></div>
                    <div>
                        <dt class="text-slate-400">Kwalificatie</dt>
                        <dd><span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $lead->qualification->badgeClasses() }}"><x-signal-dot :color="$lead->qualification->dotColor()" /> {{ $lead->qualification->label() }}</span></dd>
                    </div>
                    <div><dt class="text-slate-400">Gewenste start</dt><dd class="font-medium">{{ $lead->desired_start ?? '-' }}</dd></div>
                    <div><dt class="text-slate-400">Bron</dt><dd class="font-medium">{{ $lead->source->label() }}</dd></div>
                    <div><dt class="text-slate-400">Binnengekomen</dt><dd class="font-medium">{{ $lead->created_at->translatedFormat('j M Y H:i') }}</dd></div>
                    <div><dt class="text-slate-400">Toegewezen aan</dt><dd class="font-medium">{{ $lead->assignee?->name ?? 'Niet toegewezen' }}</dd></div>
                    <div><dt class="text-slate-400">Laatste contact</dt><dd class="font-medium">{{ $lead->last_contact_at?->translatedFormat('j M Y H:i') ?? 'Nog geen' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-slate-400">Volgende actie</dt><dd class="font-medium">{{ $lead->next_action ?? '-' }} @if ($lead->next_action_at) ({{ $lead->next_action_at->translatedFormat('j M H:i') }}) @endif</dd></div>
                    @if ($lead->description)
                        <div class="sm:col-span-2"><dt class="text-slate-400">Omschrijving</dt><dd class="font-medium whitespace-pre-line">{{ $lead->description }}</dd></div>
                    @endif
                </dl>
            </section>

            {{-- Calculaties --}}
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-white">Calculaties</h2>
                    <a href="{{ route('calculations.create', ['aanvraag' => $lead->id]) }}" class="text-xs font-semibold text-brand-400 hover:text-brand-500">+ Nieuwe calculatie</a>
                </div>
                @forelse ($lead->calculations as $calculation)
                    <a href="{{ route('calculations.show', $calculation) }}" class="mb-2 flex items-center justify-between gap-2 rounded-xl border border-surface-700 p-3 transition hover:border-brand-400">
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-white">{{ $calculation->title }}</span>
                            <span class="block text-xs text-slate-400">€ {{ number_format($calculation->totalExcl(), 2, ',', '.') }} excl. btw, {{ $calculation->lines->count() }} regels</span>
                        </span>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap {{ $calculation->status->badgeClasses() }}">{{ $calculation->status->label() }}</span>
                    </a>
                @empty
                    <p class="text-sm text-slate-500">Nog geen calculaties voor deze aanvraag.</p>
                @endforelse
            </section>

            {{-- Offertes --}}
            @if ($quotesEnabled)
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-white">Offertes</h2>
                    <a href="{{ route('quotes.create', ['aanvraag' => $lead->id]) }}" class="text-xs font-semibold text-brand-400 hover:text-brand-500">+ Nieuwe offerte</a>
                </div>
                @forelse ($lead->quotes as $quote)
                    <a href="{{ route('quotes.show', $quote) }}" class="mb-2 flex items-center justify-between gap-2 rounded-xl border border-surface-700 p-3 transition hover:border-brand-400">
                        <span>
                            <span class="block text-sm font-semibold text-white">{{ $quote->number }}</span>
                            <span class="block text-xs text-slate-400">€ {{ number_format((float) $quote->total, 2, ',', '.') }} @if ($quote->daysOpen()), {{ $quote->daysOpen() }} dagen open @endif</span>
                        </span>
                        <x-status-badge :status="$quote->status" />
                    </a>
                @empty
                    <p class="text-sm text-slate-500">Nog geen offertes.</p>
                @endforelse
            </section>
            @endif
        </div>

        <div class="space-y-4">
            {{-- Contact --}}
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-3 text-sm font-bold text-white">Contactgegevens</h2>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-slate-400">Telefoon</dt><dd class="font-medium">{{ $lead->customer->phone ?? '- ontbreekt -' }}</dd></div>
                    <div><dt class="text-slate-400">E-mail</dt><dd class="font-medium break-all">{{ $lead->customer->email ?? '-' }}</dd></div>
                    <div><dt class="text-slate-400">Adres</dt><dd class="font-medium">{{ $lead->customer->address ?? '-' }}<br>{{ trim(($lead->customer->postal_code ?? '').' '.($lead->customer->city ?? '')) ?: '' }}</dd></div>
                </dl>
            </section>

            {{-- Open taken --}}
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-3 text-sm font-bold text-white">Open taken</h2>
                @forelse ($lead->tasks as $task)
                    <div class="mb-2 rounded-lg border border-surface-700 p-2.5 text-sm">
                        <p class="font-medium text-white">{{ $task->title }}</p>
                        <p class="text-xs text-slate-400">{{ $task->deadline?->translatedFormat('j M') ?? 'geen deadline' }}, {{ $task->owner?->name ?? '-' }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Geen open taken.</p>
                @endforelse
            </section>

            {{-- Timeline preview --}}
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-white">Laatste activiteit</h2>
                    <a href="{{ route('customers.show', $lead->customer) }}" class="text-xs font-semibold text-klant-400 hover:text-klant-500">Volledig dossier →</a>
                </div>
                <div class="space-y-3">
                    @forelse ($timeline as $event)
                        @php $viaNova = in_array($event->source, [\App\Enums\ActionSource::Nova, \App\Enums\ActionSource::Voice], true); @endphp
                        <div class="flex gap-2 text-sm">
                            @if ($viaNova)
                                <x-nova-avatar class="h-6 w-6 ring-1 ring-brand-400" title="Via Nova" />
                            @else
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-surface-750 text-slate-400"><x-icon :name="$event->type->icon()" class="h-3.5 w-3.5" /></span>
                            @endif
                            <div class="min-w-0">
                                <p class="font-medium text-white">{{ $event->title }}</p>
                                <p class="text-xs text-slate-500">{{ $event->happened_at->translatedFormat('j M H:i') }}, {{ $viaNova ? 'Nova' : ($event->user?->name ?? $event->source->label()) }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nog geen activiteit.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

</x-layouts.app>
