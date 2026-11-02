<x-layouts.app title="API-documentatie">

    <x-page-header eyebrow="Koppelingen" category="lead" title="API-documentatie" subtitle="Koppel het CMS of de website aan Renovion Dash - bij formulierverzending ontstaat hier automatisch een lead." />

    <div class="max-w-3xl space-y-5">

        {{-- Status --}}
        <section class="flex items-center gap-3 rounded-2xl border p-4 {{ filled(config('renovion.lead_intake_token')) ? 'border-green-500/30 bg-green-500/10' : 'border-amber-300 bg-amber-500/10' }}">
            <x-signal-dot :color="filled(config('renovion.lead_intake_token')) ? 'green' : 'amber'" />
            @if (filled(config('renovion.lead_intake_token')))
                <p class="text-sm font-medium text-green-300">Er is een intake-token ingesteld - de API is actief. Het token zelf staat in de <code class="rounded bg-green-500/15 px-1">.env</code> (<code class="rounded bg-green-500/15 px-1">LEAD_INTAKE_TOKEN</code>) en wordt hier bewust niet getoond.</p>
            @else
                <p class="text-sm font-medium text-amber-300">Er is nog géén intake-token ingesteld. Zet <code class="rounded bg-amber-500/15 px-1">LEAD_INTAKE_TOKEN=&lt;lang willekeurig token&gt;</code> in de <code class="rounded bg-amber-500/15 px-1">.env</code> en draai <code class="rounded bg-amber-500/15 px-1">php artisan optimize:clear</code> - tot die tijd weigert de API alles.</p>
            @endif
        </section>

        {{-- Endpoint --}}
        <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <h2 class="mb-3 text-sm font-bold text-white">Endpoint: lead aanmaken</h2>
            <pre class="overflow-x-auto rounded-xl bg-navy-950 p-4 text-xs leading-relaxed text-navy-100"><code>POST {{ rtrim(config('app.url'), '/') }}/api/aanvragen
Content-Type: application/json
Accept: application/json
X-Intake-Token: &lt;geheim token&gt;</code></pre>
            <p class="mt-3 text-sm text-slate-300">Roep dit endpoint <strong>server-side</strong> aan vanuit de CMS-backend - nooit vanuit browser-JavaScript, anders ligt het token op straat. CORS staat er bewust niet voor open. Maximaal 30 verzoeken per minuut.</p>
        </section>

        {{-- Velden --}}
        <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <h2 class="mb-3 text-sm font-bold text-white">Velden</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-700 text-left text-xs font-bold text-slate-400 uppercase">
                            <th class="py-2 pr-4">Veld</th>
                            <th class="py-2 pr-4">Type</th>
                            <th class="py-2">Voorbeeld</th>
                        </tr>
                    </thead>
                    <tbody class="text-slate-300">
                        <tr class="border-b border-surface-700"><td class="py-2 pr-4 font-mono text-xs font-semibold text-white">name <span class="text-brand-400">*</span></td><td class="py-2 pr-4">string, max 255</td><td class="py-2">Familie Jansen</td></tr>
                        <tr class="border-b border-surface-700"><td class="py-2 pr-4 font-mono text-xs font-semibold text-white">email</td><td class="py-2 pr-4">e-mailadres</td><td class="py-2">jansen@example.nl</td></tr>
                        <tr class="border-b border-surface-700"><td class="py-2 pr-4 font-mono text-xs font-semibold text-white">phone</td><td class="py-2 pr-4">string, max 30</td><td class="py-2">06-12345678</td></tr>
                        <tr class="border-b border-surface-700"><td class="py-2 pr-4 font-mono text-xs font-semibold text-white">address</td><td class="py-2 pr-4">string, max 255</td><td class="py-2">Dorpsstraat 1</td></tr>
                        <tr class="border-b border-surface-700"><td class="py-2 pr-4 font-mono text-xs font-semibold text-white">postal_code</td><td class="py-2 pr-4">string, max 10</td><td class="py-2">6827 DC</td></tr>
                        <tr class="border-b border-surface-700"><td class="py-2 pr-4 font-mono text-xs font-semibold text-white">city</td><td class="py-2 pr-4">string, max 100</td><td class="py-2">Arnhem</td></tr>
                        <tr class="border-b border-surface-700"><td class="py-2 pr-4 font-mono text-xs font-semibold text-white">service</td><td class="py-2 pr-4">string, max 255</td><td class="py-2">Badkamerrenovatie</td></tr>
                        <tr class="border-b border-surface-700"><td class="py-2 pr-4 font-mono text-xs font-semibold text-white">message</td><td class="py-2 pr-4">string, max 5000</td><td class="py-2">Omschrijving uit het formulier</td></tr>
                        <tr><td class="py-2 pr-4 font-mono text-xs font-semibold text-white">desired_start</td><td class="py-2 pr-4">string, max 100</td><td class="py-2">voorjaar 2027</td></tr>
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-slate-500"><span class="text-brand-400">*</span> = verplicht. Alle overige velden zijn optioneel.</p>
        </section>

        {{-- Responses --}}
        <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <h2 class="mb-3 text-sm font-bold text-white">Responses</h2>
            <div class="space-y-2 text-sm text-slate-300">
                <p><span class="mr-1 rounded-full bg-green-500/15 px-2 py-0.5 text-xs font-bold text-green-300">201</span> Lead aangemaakt: <code class="rounded bg-surface-750 px-1.5 py-0.5 text-xs">{"id": 123, "status": "nieuw"}</code></p>
                <p><span class="mr-1 rounded-full bg-red-500/15 px-2 py-0.5 text-xs font-bold text-red-300">401</span> Token ontbreekt of is onjuist.</p>
                <p><span class="mr-1 rounded-full bg-amber-500/15 px-2 py-0.5 text-xs font-bold text-amber-300">422</span> Validatiefout, met per veld een melding in <code class="rounded bg-surface-750 px-1.5 py-0.5 text-xs">errors</code>.</p>
                <p><span class="mr-1 rounded-full bg-surface-700 px-2 py-0.5 text-xs font-bold text-slate-200">429</span> Te veel verzoeken (limiet: 30 per minuut).</p>
            </div>
        </section>

        {{-- Voorbeelden --}}
        <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <h2 class="mb-3 text-sm font-bold text-white">Voorbeeld - cURL</h2>
            <pre class="overflow-x-auto rounded-xl bg-navy-950 p-4 text-xs leading-relaxed text-navy-100"><code>curl -X POST {{ rtrim(config('app.url'), '/') }}/api/aanvragen \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-Intake-Token: GEHEIM_TOKEN" \
  -d '{
    "name": "Familie Jansen",
    "email": "jansen@example.nl",
    "phone": "06-12345678",
    "city": "Arnhem",
    "service": "Badkamerrenovatie",
    "message": "Graag een offerte voor onze badkamer van 6 m2.",
    "desired_start": "voorjaar 2027"
  }'</code></pre>

            <h2 class="mt-5 mb-3 text-sm font-bold text-white">Voorbeeld - PHP (Laravel-CMS)</h2>
            <pre class="overflow-x-auto rounded-xl bg-navy-950 p-4 text-xs leading-relaxed text-navy-100"><code>$response = Http::withHeaders(['X-Intake-Token' => config('services.renovion.token')])
    ->acceptJson()
    ->post('{{ rtrim(config('app.url'), '/') }}/api/aanvragen', [
        'name'    => $form['naam'],
        'email'   => $form['email'],
        'phone'   => $form['telefoon'],
        'city'    => $form['plaats'],
        'service' => $form['dienst'],
        'message' => $form['bericht'],
    ]);

if ($response->status() === 201) {
    // Lead staat in Renovion Dash: $response->json('id')
}</code></pre>
            <p class="mt-2 text-xs text-slate-500">De voorbeelden gebruiken de URL van deze omgeving; op live is dat het productiedomein. Bewaar het token in de CMS-config, niet hardcoded.</p>
        </section>

        {{-- Gedrag --}}
        <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <h2 class="mb-3 text-sm font-bold text-white">Wat er in Renovion Dash gebeurt</h2>
            <ul class="space-y-1.5 text-sm text-slate-300">
                <li class="flex items-start gap-2"><x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-brand-500" /> Bestaande klant wordt herkend op e-mailadres; anders ontstaat een nieuw klantdossier. Een ontbrekend telefoonnummer wordt aangevuld.</li>
                <li class="flex items-start gap-2"><x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-brand-500" /> Er ontstaat een lead met status "Nieuw" en bron "Website", inclusief timeline-event en audit trail.</li>
                <li class="flex items-start gap-2"><x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-brand-500" /> Dubbele verzending (zelfde klant en inhoud binnen 10 minuten) levert dezelfde lead op - geen duplicaten bij dubbelklikken.</li>
                <li class="flex items-start gap-2"><x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-brand-500" /> De automations volgen vanzelf: salestaak bij complete gegevens, of een e-mail om het telefoonnummer op te vragen als dat ontbreekt.</li>
            </ul>
        </section>
    </div>

</x-layouts.app>
