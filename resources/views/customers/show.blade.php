<x-layouts.app :title="$customer->name">

    <x-page-header eyebrow="Klantdossier" category="klant" :title="$customer->name" :subtitle="trim(($customer->address ? $customer->address.', ' : '').($customer->city ?? '')) ?: 'Klantdossier'">
        @if ($customer->phone)
            <a href="tel:{{ $customer->phone }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-500"><x-icon name="phone" /> Bellen</a>
        @endif
        @if ($customer->email)
            <a href="mailto:{{ $customer->email }}" class="inline-flex items-center gap-1.5 rounded-lg border border-surface-600 bg-surface-800 px-4 py-2 text-sm font-semibold text-white transition hover:border-brand-400"><x-icon name="envelope" /> Mailen</a>
        @endif
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-3">

        {{-- Timeline: het centrale dossier --}}
        <div class="lg:col-span-2">
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-4 text-sm font-bold text-white">Timeline</h2>

                <form method="POST" action="{{ route('customers.notes.store', $customer) }}" class="mb-5 flex gap-2">
                    @csrf
                    <input type="text" name="body" required placeholder="Notitie toevoegen…"
                           class="flex-1 rounded-xl border-surface-600 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <button type="submit" class="rounded-xl bg-navy-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-navy-800">+</button>
                </form>

                <div class="space-y-4">
                    @forelse ($customer->timelineEvents as $event)
                        @php $viaNova = in_array($event->source, [\App\Enums\ActionSource::Nova, \App\Enums\ActionSource::Voice], true); @endphp
                        <div class="relative flex gap-3 pb-4 {{ ! $loop->last ? 'border-l border-surface-700 pl-5 before:absolute before:top-0 before:-left-3 before:content-[""]' : 'pl-5' }}" style="margin-left: 0.6rem;">
                            @if ($viaNova)
                                <x-nova-avatar class="absolute top-0 -left-3 h-6 w-6 ring-1 ring-brand-400" title="Via Nova" />
                            @else
                                <span class="absolute top-0 -left-3 flex h-6 w-6 items-center justify-center rounded-full bg-surface-750 text-slate-400"><x-icon :name="$event->type->icon()" class="h-3.5 w-3.5" /></span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-white">{{ $event->title }}</p>
                                @if ($event->body)
                                    <p class="mt-0.5 text-sm whitespace-pre-line text-slate-300">{{ $event->body }}</p>
                                @endif
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $event->happened_at->translatedFormat('j M Y, H:i') }}
                                    @if ($viaNova)
                                       , Nova{{ $event->user ? ', bevestigd door '.$event->user->name : '' }}
                                    @else
                                       , {{ $event->user?->name ?? $event->source->label() }}
                                    @endif
                                   , {{ $event->type->label() }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nog geen activiteit in dit dossier.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="space-y-4">
            {{-- Contact --}}
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-3 text-sm font-bold text-white">Gegevens</h2>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-slate-400">Telefoon</dt><dd class="font-medium">{{ $customer->phone ?? '-' }}</dd></div>
                    <div><dt class="text-slate-400">E-mail</dt><dd class="font-medium break-all">{{ $customer->email ?? '-' }}</dd></div>
                    <div><dt class="text-slate-400">Adres</dt><dd class="font-medium">{{ $customer->address ?? '-' }}<br>{{ trim(($customer->postal_code ?? '').' '.($customer->city ?? '')) }}</dd></div>
                </dl>
            </section>

            {{-- Klantportaal (briefing §10) --}}
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-3 text-sm font-bold text-white">Klantportaal</h2>
                @if ($customer->portalUser)
                    <p class="flex items-center gap-1.5 text-sm font-medium text-green-300"><x-icon name="check" class="h-4 w-4" /> Account actief</p>
                    <p class="mt-1 text-xs text-slate-500">Inloggen met {{ $customer->portalUser->email }} sinds {{ $customer->portalUser->created_at->translatedFormat('j M Y') }}.</p>
                    <form method="POST" action="{{ route('customers.portal', $customer) }}" class="mt-3" onsubmit="return confirm('Nieuw wachtwoord genereren? Het oude wachtwoord werkt daarna niet meer.');">
                        @csrf
                        <button type="submit" class="w-full rounded-xl border border-surface-600 bg-surface-800 py-2 text-sm font-semibold text-slate-300 transition hover:bg-surface-850">Wachtwoord resetten</button>
                    </form>
                @else
                    <p class="text-xs text-slate-500">De klant volgt hiermee zelf de voortgang, foto's en acties via {{ rtrim(config('app.url'), '/') }}/portaal.</p>
                    <form method="POST" action="{{ route('customers.portal', $customer) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="w-full rounded-xl bg-brand-500 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600" @disabled(blank($customer->email))>
                            Portaal-account aanmaken
                        </button>
                    </form>
                    @if (blank($customer->email))
                        <p class="mt-2 text-xs text-amber-400">Vul eerst een e-mailadres in bij deze klant.</p>
                    @endif
                @endif
            </section>

            {{-- Aanvragen --}}
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-white">Aanvragen</h2>
                    <a href="{{ route('leads.create', ['klant' => $customer->id]) }}" class="text-xs font-semibold text-brand-400 hover:text-brand-500">+ Nieuw</a>
                </div>
                @forelse ($customer->leads as $lead)
                    <a href="{{ route('leads.show', $lead) }}" class="mb-2 flex items-center justify-between gap-2 rounded-lg border border-surface-700 p-2.5 text-sm transition hover:border-brand-400">
                        <span class="truncate font-medium text-white">{{ $lead->service ?? 'Aanvraag' }}</span>
                        <x-status-badge :status="$lead->status" />
                    </a>
                @empty
                    <p class="text-sm text-slate-500">Geen aanvragen.</p>
                @endforelse
            </section>

            {{-- Offertes --}}
            @if (config('renovion.modules.quotes'))
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-3 text-sm font-bold text-white">Offertes</h2>
                @forelse ($customer->quotes as $quote)
                    <a href="{{ route('quotes.show', $quote) }}" class="mb-2 flex items-center justify-between gap-2 rounded-lg border border-surface-700 p-2.5 text-sm transition hover:border-brand-400">
                        <span>
                            <span class="block font-medium text-white">{{ $quote->number }}</span>
                            <span class="text-xs text-slate-400">€ {{ number_format((float) $quote->total, 2, ',', '.') }}</span>
                        </span>
                        <x-status-badge :status="$quote->status" />
                    </a>
                @empty
                    <p class="text-sm text-slate-500">Geen offertes.</p>
                @endforelse
            </section>
            @endif

            {{-- Projecten --}}
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-3 text-sm font-bold text-white">Projecten</h2>
                @forelse ($customer->projects as $project)
                    <a href="{{ route('projects.show', $project) }}" class="mb-2 flex items-center justify-between gap-2 rounded-lg border border-surface-700 p-2.5 text-sm transition hover:border-brand-400">
                        <span class="truncate font-medium text-white">{{ $project->name }}</span>
                        <x-status-badge :status="$project->status" />
                    </a>
                @empty
                    <p class="text-sm text-slate-500">Geen projecten.</p>
                @endforelse
            </section>

            {{-- Documenten --}}
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-3 text-sm font-bold text-white">Documenten</h2>
                @forelse ($customer->documents as $document)
                    <a href="{{ route('documents.show', $document) }}" class="mb-2 flex items-center gap-2 rounded-lg border border-surface-700 p-2.5 text-sm transition hover:border-brand-400">
                        <x-icon name="paper-clip" class="text-slate-500" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-white">{{ $document->name }}</span>
                            <span class="text-xs text-slate-400">{{ $document->category->label() }}, {{ $document->created_at->translatedFormat('j M Y') }}</span>
                        </span>
                    </a>
                @empty
                    <p class="mb-3 text-sm text-slate-500">Geen documenten.</p>
                @endforelse

                <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="mt-2 space-y-2">
                    @csrf
                    <input type="hidden" name="documentable_type" value="customer">
                    <input type="hidden" name="documentable_id" value="{{ $customer->id }}">
                    <input type="file" name="file" required class="w-full text-xs text-slate-400 file:mr-2 file:rounded-lg file:border-0 file:bg-white/10 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-slate-200">
                    <button type="submit" class="w-full rounded-lg bg-surface-750 py-2 text-xs font-semibold text-slate-200 transition hover:bg-surface-700">Uploaden</button>
                </form>
            </section>
        </div>
    </div>

</x-layouts.app>
