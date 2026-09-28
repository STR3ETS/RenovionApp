# Renovion Dash

CRM + projectmanagementsysteem voor **Renovion** (renovatiebedrijf, Arnhem — stucwerk, schilderwerk, tegelwerk, badkamerrenovaties, complete renovaties). Gebouwd voor Imad en Raphael.

**Leidende spec: [Renovion_Technische_Briefing_Boyd_v1.pdf](Renovion_Technische_Briefing_Boyd_v1.pdf)** (v1.0, 28 sept 2026, "briefing v2") — lees die bij twijfel over functionele scope. De oudere [technische-briefing.txt](technische-briefing.txt) blijft naslagwerk voor details die v2 niet herhaalt (bijv. automation-templates), maar v2 wint bij conflict. De mockups in de PDF (§18) zijn de stylingreferentie.

## Leidend principe

> Eén omgeving voor het hele proces: aanvraag → calculatie → offerte → contract → projectfasering → uitvoering met foto-bewijs → oplevering → nazorg. Het systeem laat zelf zien wat vandaag aandacht vraagt; Nova automatiseert, de gebruiker houdt regie.

- **Mobile-first, geen uitzonderingen.** Uitvoerders werken volledig vanaf de telefoon.
- **Actie gaat vóór informatie.** De Aandacht-module toont uitsluitend uitzonderingen.
- **Nova stelt voor, de gebruiker beslist** — behalve waar een automation expliciet op "automatisch uitvoeren" staat (briefing §12: per regel instelbaar: alleen signaleren / eerst bevestigen / automatisch).
- **AI-output is altijd een concept** dat een mens bevestigt (calculatieregels, offerteteksten, planningen).

## Domein (briefing v2)

- **Leadpipeline:** Nieuw → Contact → Intake → Offerte → Akkoord → Project (+ Verloren / On hold).
- **Projectfasen 0–8 (§7, `ProjectPhase::NAMES`):** Opdracht & overdracht → Voortraject → Werkvoorbereiding → Inkoop & planning → Uitvoering → Controle & klantacties → Vooroplevering → Oplevering → Nazorg. Elk project krijgt ze automatisch bij aanmaak (`Project::booted`).
- **Fasestatus (`PhaseStatus`):** niet gestart / bezig / wacht op klant / wacht op leverancier / geblokkeerd / gereed. Kleuren: groen = op schema, oranje = aandacht/wachten, rood = geblokkeerd, grijs = niet gestart.
- **Taakstatus:** Te doen → Bezig → Wacht op → Afgerond.
- **Rollen (`UserRole`):** Admin/MT, Sales, Projectleider, Werkvoorbereider, Uitvoerder (voorheen "vakman"; alleen eigen projecten/taken/planning, extreem simpel). De **Klant**-rol (klantportaal) volgt in de portaal-sprint. Gates: `manage-crm` (iedereen behalve Uitvoerder), `manage-team` (alleen Admin).
- **Klantdossier:** één chronologische timeline per klant. **Audit trail** bij elke belangrijke actie: wie → wat → wanneer → oud → nieuw → bron (Handmatig / Voice / Nova / Automation / Website / WhatsApp).

## Nog te bouwen (briefing v2, volgorde §17 + MVP-keuze §16)

MVP eerst als één verticale flow: lead → calculatie → offerte → akkoord → project → één uitvoeringsfase → foto-bewijs → klantakkoord → opleverrapport. Daarna verbreden.

1. ✔ Sprint 1: huisstijl v2, rollen, projectfasen-fundament, globale zoekfunctie, fotokaarten.
2. ✔ Sprint 2: leadkwalificatie (koud/warm/heet, gewenste start), contactmomenten (`Lead::logContact`, `POST aanvragen/{lead}/contactmomenten`), stille-aanvraag-signaal in Aandacht, Nova-actie `log_contact`.
3. **Calculatiemodule** (§5): kostendatabase (Archidat-boeken als geversioneerde prijsbibliotheek — licentie vereist, Renovion-praktijkprijzen als eigen set), invoer handmatig/spraak/PDF, AI-regels altijd eerst tonen.
4. **Offerte-editor** (§6): EasyDash-concept (links blokken, midden live document, rechts templates), klantview met digitaal ondertekenen (click-to-sign + audit), versies v1/v2/v3 als immutable snapshots, nummering REN-{jaar}-{volgnr}.
5. Project core: werkpakketten, checklists, goedkeuringsgates per fase.
6. **Foto-bewijs** (§8): verplichte foto's per checklistitem (bijv. "min. 3 vóór dichtzetten"), taak kan niet dicht zonder bewijs, klant tekent "gezien en akkoord".
7. **Klantportaal** (§9): Klant-rol, vereenvoudigde tijdlijn, "deze week", "actie van u nodig", alleen klantzichtbare content (nooit marge/interne notities) — zichtbaarheid op objectniveau (internal / client-visible).
8. **Teamchat** (§10): kanalen Team/Projecten, mentions, taak/issue vanuit chat, Nova praat mee met bevestigingsknoppen.
9. **Opleverrapport** (§13): automatisch PDF met foto-bewijs, restpunten, handtekeningen.
10. Hardening: rechten per veld, notificatie-rate-limiting, soft deletes waar relevant.

## Huisstijl v2 (mockups §18)

- **Kleuren:** oranje (primair/CTA) `#F85B0B` = `brand-500`; navy `#070724` = `navy-950` (sidebar) en `#0B0B2B` = `navy-900`; statuskleuren groen/oranje/rood/grijs. Tokens in [app.css](resources/css/app.css). Staalblauw (`steel-*`) is legacy en wordt uitgefaseerd.
- **Look:** witte kaarten (`rounded-2xl border-gray-200`), veel witruimte, sidebar met iconen + oranje actieve pill, topbar met zoekbalk + "+ Nieuw", projectkaarten met foto (nu placeholder-gradient; foto-bewijs levert straks de echte omslag), voortgangsbalken, avatar-stacks.
- **Font:** Inter Tight (bunny.net). **Logo:** [renovion-logo.svg](public/images/renovion-logo.svg) — alleen op donkere achtergrond.
- **Geen emoji's in de UI** (keuze Raphael): `<x-icon name="...">` (heroicons outline, [icon.blade.php](resources/views/components/icon.blade.php)), `<x-signal-dot>`, `<x-status-badge>`, `<x-stat-tile>`, `<x-project-card>`, `<x-phase-stepper>`.
- Alle UI-teksten Nederlands. Bedrijfsgegevens: Mercatorweg 28, 6827 DC Arnhem · info@renovion.nl · +31 6 395 353 00 · KVK 95384782.

## Nova (AI-assistent)

[NovaAssistant](app/Services/NovaAssistant.php) gebruikt `anthropic-ai/sdk` (PHP), model uit `RENOVION_AI_MODEL` (nu `claude-sonnet-5`), key in `.env`. Verplicht patroon (briefing §11):

```
Voice/tekst → intent → gestructureerde JSON-action → validatie → permissions → preview/bevestiging → backend-actie → audit log
```

- Acties v1: `create_task`, `create_appointment`, `create_note`. Nieuwe actie = tool in `tools()` + `validator()` + preview + execute + `ACTIONS`.
- Voice via browser Web Speech API (nl-NL). Endpoints: `POST /nova`, `POST /nova/uitvoeren`, `GET /nova/briefing` (dagbriefing, 3 uur gecachet). UI: chat-sheet in [app.blade.php](resources/views/components/layouts/app.blade.php) + `Alpine.data('nova')` in [app.js](resources/js/app.js). Nova's gezicht: `public/nova.jpg` (`<x-nova-avatar>`).

## Stack & omgeving

- Laravel 13 · PHP 8.5 lokaal (8.4 op live/Plesk) · Tailwind CSS 4 · Vite 8 · MySQL (`renovionapp`) · Laragon, `https://renovionapp.test`.
- **Frontend: Blade + Tailwind + Alpine.js** — bewuste keuze, géén Livewire/Inertia/Filament. Interactiviteit met Alpine + fetch naar JSON-endpoints.
- Queue/cache/sessies: database-driver. Herbruikbare concepten uit EasyDash/EazyOnline (offerte-editor!) en EazyChats (teamchat): zie zustermappen in `c:\laragon\www\`.
- **Seeders:** `TeamSeeder` (4 accounts, idempotent, wachtwoorden worden nooit overschreven) draait altijd; `DemoSeeder` alleen local/testing. Live deploy: `git pull` + `php artisan migrate --force` + **altijd `php artisan optimize:clear`** (config/route-caches overleven pulls).

## Tijdelijk uitgeschakelde modules

**Offertes en Automations staan uit** (code-default `false` in [config/renovion.php](config/renovion.php)); aanzetten alleen via `.env` (`MODULE_QUOTES=true` / `MODULE_AUTOMATIONS=true`) + caches verversen. Uit = geen routes, geen menu, geen signalen. In tests staan beide aan (phpunit.xml). De offertemodule gaat bij de offerte-editor-sprint in vernieuwde vorm weer aan.

## Automations & Aandacht (gebouwd, fundament voor Nova-rules §12)

- **Automations** ([app/Automations](app/Automations)): interface `Automation` + `AutomationRegistry::CLASSES`; idempotent via `AutomationRun::claim()`. Draait via `php artisan automations:run`, elke 15 min gescheduled ([routes/console.php](routes/console.php)) — scheduler moet draaien (`schedule:work` lokaal / cron live). Wordt later uitgebreid met per-regel modus: signaleren / bevestigen / automatisch.
- **Aandacht** ([AttentionService](app/Services/AttentionService.php)): alleen uitzonderingen; badge-teller 5 min gecachet (`attention-count`).
