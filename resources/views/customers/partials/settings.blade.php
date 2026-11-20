@php
    use App\Enums\CustomerType;
@endphp

{{-- Gegevens: wat we van de klant weten en waarmee we voor hem rekenen --}}
<section x-data="{ edit: {{ $errors->any() ? 'true' : 'false' }} }" class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
    <div class="mb-3 flex items-center justify-between gap-2">
        <h2 class="text-sm font-bold text-ink">Gegevens</h2>
        <button type="button" @click="edit = !edit" class="text-xs font-semibold text-brand-400 hover:text-brand-300"
                x-text="edit ? 'Sluiten' : 'Bewerken'"></button>
    </div>

    <div x-show="!edit">
        <p class="mb-3 flex flex-wrap items-center gap-2">
            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $customer->type->badgeClasses() }}">{{ $customer->type->label() }}</span>
            <span class="text-xs text-muted">Klantnummer {{ $customer->number }}</span>
        </p>
        <dl class="space-y-2 text-sm">
            @if ($customer->contact_name)
                <div><dt class="text-muted">Contactpersoon</dt><dd class="font-medium">{{ $customer->contact_name }}</dd></div>
            @endif
            <div><dt class="text-muted">Telefoon</dt><dd class="font-medium">{{ $customer->phone ?? '-' }}</dd></div>
            <div><dt class="text-muted">E-mail</dt><dd class="font-medium break-all">{{ $customer->email ?? '-' }}</dd></div>
            <div><dt class="text-muted">Adres</dt><dd class="font-medium">{{ $customer->address ?? '-' }}<br>{{ trim(($customer->postal_code ?? '').' '.($customer->city ?? '')) }}</dd></div>
            @if ($customer->coc_number || $customer->vat_number)
                <div><dt class="text-muted">KvK en btw</dt><dd class="font-medium">{{ collect([$customer->coc_number, $customer->vat_number])->filter()->join(', ') }}</dd></div>
            @endif
            <div>
                <dt class="text-muted">Betaaltermijn</dt>
                <dd class="font-medium">{{ $customer->payment_days }} dagen, {{ $customer->reminders ? 'met herinnering' : 'geen herinnering' }}</dd>
            </div>
            @if ($customer->relation)
                <div><dt class="text-muted">Relatie</dt><dd class="font-medium">{{ $customer->relation }}</dd></div>
            @endif
        </dl>
    </div>

    <form x-show="edit" x-cloak method="POST" action="{{ route('customers.update', $customer) }}" class="space-y-3">
        @csrf
        @method('PATCH')

        <x-field label="Naam" name="name">
            <input type="text" name="name" id="name" value="{{ old('name', $customer->name) }}" required class="w-full rounded-xl border-surface-600 text-sm">
        </x-field>

        <x-field label="Soort" name="type">
            <select name="type" id="type" class="w-full rounded-xl border-surface-600 text-sm">
                @foreach (CustomerType::cases() as $case)
                    <option value="{{ $case->value }}" @selected(old('type', $customer->type->value) === $case->value)>
                        {{ $case->label() }}, standaard {{ $case->defaultPaymentDays() }} dagen
                    </option>
                @endforeach
            </select>
        </x-field>

        <x-field label="Contactpersoon" name="contact_name">
            <input type="text" name="contact_name" id="contact_name" value="{{ old('contact_name', $customer->contact_name) }}" class="w-full rounded-xl border-surface-600 text-sm">
        </x-field>

        <div class="grid grid-cols-2 gap-3">
            <x-field label="Telefoon" name="phone">
                <input type="text" name="phone" id="phone" value="{{ old('phone', $customer->phone) }}" class="w-full rounded-xl border-surface-600 text-sm">
            </x-field>
            <x-field label="E-mail" name="email">
                <input type="email" name="email" id="email" value="{{ old('email', $customer->email) }}" class="w-full rounded-xl border-surface-600 text-sm">
            </x-field>
        </div>

        <x-field label="Adres" name="address">
            <input type="text" name="address" id="address" value="{{ old('address', $customer->address) }}" class="w-full rounded-xl border-surface-600 text-sm">
        </x-field>

        <div class="grid grid-cols-2 gap-3">
            <x-field label="Postcode" name="postal_code">
                <input type="text" name="postal_code" id="postal_code" value="{{ old('postal_code', $customer->postal_code) }}" class="w-full rounded-xl border-surface-600 text-sm">
            </x-field>
            <x-field label="Plaats" name="city">
                <input type="text" name="city" id="city" value="{{ old('city', $customer->city) }}" class="w-full rounded-xl border-surface-600 text-sm">
            </x-field>
            <x-field label="KvK-nummer" name="coc_number">
                <input type="text" name="coc_number" id="coc_number" value="{{ old('coc_number', $customer->coc_number) }}" class="w-full rounded-xl border-surface-600 text-sm">
            </x-field>
            <x-field label="Btw-nummer" name="vat_number">
                <input type="text" name="vat_number" id="vat_number" value="{{ old('vat_number', $customer->vat_number) }}" class="w-full rounded-xl border-surface-600 text-sm">
            </x-field>
        </div>

        <x-field label="Betaaltermijn in dagen" name="payment_days">
            <input type="number" name="payment_days" id="payment_days" min="0" max="120" value="{{ old('payment_days', $customer->payment_days) }}"
                   placeholder="Leeg is de standaard van de soort" class="w-full rounded-xl border-surface-600 text-sm">
        </x-field>

        <label class="flex items-start gap-2 text-sm text-ink2">
            <input type="checkbox" name="reminders" value="1" @checked(old('reminders', $customer->reminders)) class="mt-0.5 rounded">
            <span>Herinnering sturen als een factuur over de vervaldatum is</span>
        </label>

        <x-field label="Relatie" name="relation">
            <input type="text" name="relation" id="relation" value="{{ old('relation', $customer->relation) }}"
                   placeholder="Bijvoorbeeld via welke aannemer" class="w-full rounded-xl border-surface-600 text-sm">
        </x-field>

        <button type="submit" class="btn pri w-full">Opslaan</button>
    </form>
</section>

{{-- De persoonlijke opdrachtlink: een zakelijke klant schiet zo zonder inloggen werk in --}}
@if ($customer->isBusiness() && Route::has('customer-orders.create'))
    <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
        <h2 class="mb-1 text-sm font-bold text-ink">Opdracht <span class="text-brand-500">inschieten</span></h2>
        <p class="text-xs text-faint">
            Met deze persoonlijke link geeft de klant zelf een opdracht door. Die komt herkend binnen bij de aanvraagmail.
        </p>

        @if ($customer->portal_token)
            @php($link = route('customer-orders.create', $customer->portal_token))
            <div x-data="{ copied: false }" class="mt-3">
                <input type="text" readonly value="{{ $link }}" onclick="this.select()"
                       class="w-full rounded-xl border-surface-600 text-xs text-ink2">
                <div class="mt-2 flex gap-2">
                    <button type="button"
                            @click="navigator.clipboard.writeText(@js($link)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                            class="flex-1 rounded-full bg-brand-500 py-2 text-xs font-bold text-white transition hover:bg-brand-600">
                        <span x-show="!copied">Link kopiëren</span>
                        <span x-show="copied" x-cloak>Gekopieerd</span>
                    </button>
                    <form method="POST" action="{{ route('customers.portal-link', $customer) }}"
                          onsubmit="return confirm('Nieuwe link maken? De oude link werkt daarna niet meer.')">
                        @csrf
                        <input type="hidden" name="vernieuwen" value="1">
                        <button type="submit" class="btn sm">Vernieuwen</button>
                    </form>
                </div>
            </div>
        @else
            <form method="POST" action="{{ route('customers.portal-link', $customer) }}" class="mt-3">
                @csrf
                <button type="submit" class="w-full rounded-full border border-surface-600 py-2 text-sm font-semibold text-ink transition hover:border-brand-500 hover:text-ink">Link aanmaken</button>
            </form>
        @endif
    </section>
@endif
