<x-layouts.app :title="$customer->name">

    <x-page-header :title="$customer->name" :subtitle="trim(($customer->address ? $customer->address.', ' : '').($customer->city ?? '')) ?: 'Klantdossier'">
        @if ($customer->phone)
            <a href="tel:{{ $customer->phone }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-500"><x-icon name="phone" /> Bellen</a>
        @endif
        @if ($customer->email)
            <a href="mailto:{{ $customer->email }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-navy-900 transition hover:border-brand-400"><x-icon name="envelope" /> Mailen</a>
        @endif
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-3">

        {{-- Timeline: het centrale dossier --}}
        <div class="lg:col-span-2">
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-4 text-sm font-bold text-navy-900">Timeline</h2>

                <form method="POST" action="{{ route('customers.notes.store', $customer) }}" class="mb-5 flex gap-2">
                    @csrf
                    <input type="text" name="body" required placeholder="Notitie toevoegen…"
                           class="flex-1 rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <button type="submit" class="rounded-xl bg-navy-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-navy-800">+</button>
                </form>

                <div class="space-y-4">
                    @forelse ($customer->timelineEvents as $event)
                        @php $viaNova = in_array($event->source, [\App\Enums\ActionSource::Nova, \App\Enums\ActionSource::Voice], true); @endphp
                        <div class="relative flex gap-3 pb-4 {{ ! $loop->last ? 'border-l border-gray-200 pl-5 before:absolute before:top-0 before:-left-3 before:content-[""]' : 'pl-5' }}" style="margin-left: 0.6rem;">
                            @if ($viaNova)
                                <x-nova-avatar class="absolute top-0 -left-3 h-6 w-6 ring-1 ring-brand-400" title="Via Nova" />
                            @else
                                <span class="absolute top-0 -left-3 flex h-6 w-6 items-center justify-center rounded-full bg-gray-100 text-gray-500"><x-icon :name="$event->type->icon()" class="h-3.5 w-3.5" /></span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-navy-900">{{ $event->title }}</p>
                                @if ($event->body)
                                    <p class="mt-0.5 text-sm whitespace-pre-line text-gray-600">{{ $event->body }}</p>
                                @endif
                                <p class="mt-1 text-xs text-gray-400">
                                    {{ $event->happened_at->translatedFormat('j M Y · H:i') }}
                                    @if ($viaNova)
                                        · Nova{{ $event->user ? ' · bevestigd door '.$event->user->name : '' }}
                                    @else
                                        · {{ $event->user?->name ?? $event->source->label() }}
                                    @endif
                                    · {{ $event->type->label() }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">Nog geen activiteit in dit dossier.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="space-y-4">
            {{-- Contact --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-navy-900">Gegevens</h2>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-gray-500">Telefoon</dt><dd class="font-medium">{{ $customer->phone ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">E-mail</dt><dd class="font-medium break-all">{{ $customer->email ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Adres</dt><dd class="font-medium">{{ $customer->address ?? '—' }}<br>{{ trim(($customer->postal_code ?? '').' '.($customer->city ?? '')) }}</dd></div>
                </dl>
            </section>

            {{-- Aanvragen --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-navy-900">Aanvragen</h2>
                    <a href="{{ route('leads.create', ['klant' => $customer->id]) }}" class="text-xs font-semibold text-steel-600 hover:text-steel-500">+ Nieuw</a>
                </div>
                @forelse ($customer->leads as $lead)
                    <a href="{{ route('leads.show', $lead) }}" class="mb-2 flex items-center justify-between gap-2 rounded-lg border border-gray-200 p-2.5 text-sm transition hover:border-brand-400">
                        <span class="truncate font-medium text-navy-900">{{ $lead->service ?? 'Aanvraag' }}</span>
                        <x-status-badge :status="$lead->status" />
                    </a>
                @empty
                    <p class="text-sm text-gray-400">Geen aanvragen.</p>
                @endforelse
            </section>

            {{-- Offertes --}}
            @if (config('renovion.modules.quotes'))
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-navy-900">Offertes</h2>
                @forelse ($customer->quotes as $quote)
                    <a href="{{ route('quotes.show', $quote) }}" class="mb-2 flex items-center justify-between gap-2 rounded-lg border border-gray-200 p-2.5 text-sm transition hover:border-brand-400">
                        <span>
                            <span class="block font-medium text-navy-900">{{ $quote->number }}</span>
                            <span class="text-xs text-gray-500">€ {{ number_format((float) $quote->total, 2, ',', '.') }}</span>
                        </span>
                        <x-status-badge :status="$quote->status" />
                    </a>
                @empty
                    <p class="text-sm text-gray-400">Geen offertes.</p>
                @endforelse
            </section>
            @endif

            {{-- Projecten --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-navy-900">Projecten</h2>
                @forelse ($customer->projects as $project)
                    <a href="{{ route('projects.show', $project) }}" class="mb-2 flex items-center justify-between gap-2 rounded-lg border border-gray-200 p-2.5 text-sm transition hover:border-brand-400">
                        <span class="truncate font-medium text-navy-900">{{ $project->name }}</span>
                        <x-status-badge :status="$project->status" />
                    </a>
                @empty
                    <p class="text-sm text-gray-400">Geen projecten.</p>
                @endforelse
            </section>

            {{-- Documenten --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-navy-900">Documenten</h2>
                @forelse ($customer->documents as $document)
                    <a href="{{ route('documents.show', $document) }}" class="mb-2 flex items-center gap-2 rounded-lg border border-gray-200 p-2.5 text-sm transition hover:border-brand-400">
                        <x-icon name="paper-clip" class="text-gray-400" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-navy-900">{{ $document->name }}</span>
                            <span class="text-xs text-gray-500">{{ $document->category->label() }} · {{ $document->created_at->translatedFormat('j M Y') }}</span>
                        </span>
                    </a>
                @empty
                    <p class="mb-3 text-sm text-gray-400">Geen documenten.</p>
                @endforelse

                <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="mt-2 space-y-2">
                    @csrf
                    <input type="hidden" name="documentable_type" value="customer">
                    <input type="hidden" name="documentable_id" value="{{ $customer->id }}">
                    <input type="file" name="file" required class="w-full text-xs text-gray-500 file:mr-2 file:rounded-lg file:border-0 file:bg-navy-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-navy-700">
                    <button type="submit" class="w-full rounded-lg bg-gray-100 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-200">Uploaden</button>
                </form>
            </section>
        </div>
    </div>

</x-layouts.app>
