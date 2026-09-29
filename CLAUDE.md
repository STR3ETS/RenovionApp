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
- **Rollen (`UserRole`):** Admin/MT, Sales, Projectleider, Werkvoorbereider, Uitvoerder (voorheen "vakman"; alleen eigen projecten/taken/planning, extreem simpel) en **Klant** (alleen het portaal; `users.customer_id` koppelt aan het klantdossier). Gates: `internal` (iedereen behalve Klant — hele interne omgeving), `manage-crm` (iedereen behalve Uitvoerder en Klant), `manage-team` (alleen Admin; teambeheer toont/maakt geen klant-accounts — die ontstaan via de klantpagina).
- **Klantdossier:** één chronologische timeline per klant. **Audit trail** bij elke belangrijke actie: wie → wat → wanneer → oud → nieuw → bron (Handmatig / Voice / Nova / Automation / Website / WhatsApp).

## Nog te bouwen (briefing v2, volgorde §17 + MVP-keuze §16)

MVP eerst als één verticale flow: lead → calculatie → offerte → akkoord → project → één uitvoeringsfase → foto-bewijs → klantakkoord → opleverrapport. Daarna verbreden.

1. ✔ Sprint 1: huisstijl v2, rollen, projectfasen-fundament, globale zoekfunctie, fotokaarten.
2. ✔ Sprint 2: leadkwalificatie (koud/warm/heet, gewenste start), contactmomenten (`Lead::logContact`, `POST aanvragen/{lead}/contactmomenten`), stille-aanvraag-signaal in Aandacht, Nova-actie `log_contact`.
3. ✔ Sprint 3 — **Calculatiemodule** (§5): kostendatabase `PriceItem` (bron/editie/eenheid/indexfactor/opslag, `PriceLibrarySeeder` met Renovion-praktijkprijzen 2026, draait ook op productie; Archidat-import volgt zodra licentie rond is), `Calculation` + `CalculationLine` met prijssnapshot per regel (`Calculation::addLine`), totalen (subtotaal → onvoorzien% → marge% → btw%), definitief = regels op slot, [CalculationAssistant](app/Services/CalculationAssistant.php) vertaalt tekst/spraak naar regels die de gebruiker eerst controleert (`POST calculaties/ai-voorstel`), prijsbibliotheek-picker via `GET prijsitems`. PDF-import nog open.
4. ✔ Sprint 4 — **Offerte-editor** (§6): vaste blokken uit de briefing met templates per werktype ([QuoteTemplates](app/Support/QuoteTemplates.php)), tabbladen Overzicht/Teksten (editor + live voorbeeld)/Preview op de offertepagina, klantlink zonder inlog (`GET offerte/{public_token}`) met digitaal ondertekenen (naam+datum+IP in audit, `AcceptQuote` → project), aanpassing aanvragen en afwijzen, versiebeheer (versturen bevriest snapshot in `quote_versions`, nieuwe versie = wijzigingslog verplicht + terug naar concept), calculatie→offerte met één actie ([CreateQuoteFromCalculation](app/Actions/CreateQuoteFromCalculation.php): commerciële posten, marge verdeeld, stelposten apart), nummering REN-{jaar}-{volgnr}. Nog open: échte PDF-generatie (nu print-naar-PDF) en mailverzending (SMTP ontbreekt).
5. ✔ Sprint 5 — **Project core** (§7): werkpakketten per fase (`WorkPackage`, status = `PhaseStatus`) met checklists (`ChecklistItem`, incl. `requires_photos` voor de foto-bewijs-sprint), Uitvoering-tab op projectdetail (fasebeheer: status wijzigen incl. wacht/geblokkeerd, verantwoordelijke), werkpakket-detailpagina (mockup "Elektra begane grond": afvinken, "Taak afronden" pas bij complete checklist — uitvoerders mogen dit op eigen projecten), goedkeuringsgates (`POST fasen/{phase}/vrijgeven`: alle werkpakketten gereed → fase gereed + wie/wanneer/notitie + volgende fase start automatisch), voortgang% wordt berekend (`Project::syncProgress`, niet meer handmatig), blokkades en verlopen werkpakket-deadlines in Aandacht. Toegang via `Project::isAccessibleBy`.
6. ✔ Sprint 6 — **Foto-bewijs** (§9): `Photo` gekoppeld aan fase/werkpakket/checklistitem (nooit een losse galerij), upload door uitvoerders op eigen projecten (`POST projecten/{project}/fotos`, multiple, camera-capture), streaming uit private storage (`GET fotos/{photo}`), `client_visible`-vlag (alleen manage-crm togglet), handhaving: checklistitem met `requires_photos` kan niet af zonder bewijs en `WorkPackage::evidenceComplete()` blokkeert "Taak afronden", Foto's-tab op projectdetail met fase-filter (mockup "Foto's & voortgang"), foto-upload per checklistitem op het werkpakket-scherm, omslagfoto valt automatisch terug op het laatste klantzichtbare foto-bewijs (`Project::coverPhotoPath`). Nog open: thumbnails/verkleinen server-side, issue vanuit foto (chat-sprint), klant tekent "gezien en akkoord" bij mijlpalen (klantportaal-sprint).
7. ✔ Sprint 7 — **Klantportaal** (§10): Klant-rol + portaal-account per klantdossier (aanmaken/wachtwoord-reset vanaf de klantpagina, wachtwoord eenmalig in de flash — geen SMTP), eigen portaal-layout op `/portaal` ([PortalController](app/Http/Controllers/PortalController.php)): voortgang% + "Op schema/Aandacht", vereenvoudigde klant-tijdlijn (6 groepen over de 9 fasen), tabs Overzicht/Planning/Foto's, "Deze week" uit de planning, "Actie van u nodig" (wacht-op-klant-fasen, "gezien en akkoord" per afgeronde fase → `client_approved_at/by` + audit bron website, open offertes → klantlink), alleen klantzichtbare foto's. Klant komt de interne omgeving niet in (`can:internal` om alle interne routes). Nog open: Berichten-tab (chat-sprint), documenten voor de klant.
8. ✔ Sprint 8 — **Teamchat** (§11): `ChatChannel` (teamkanalen + automatisch één kanaal per project via `Project::booted`) en `ChatMessage` (tekst, fotobijlage, `nova`-json), unread counters per gebruiker (`chat_channel_user.last_read_at`, badge 60s gecachet), Alpine-component `chat` (verzenden, 8s-polling via `GET chat/{channel}/berichten?after=`, dicteren, foto-upload), @mention-highlight, taak vanuit bericht (`POST chat/berichten/{message}/taak`), **@Nova in de chat**: propose → navy voorstelkaart met "Ja, doe maar"/"Eerst aanpassen", bevestigen via `POST chat/berichten/{message}/nova-bevestigen` (alleen manage-crm). Sidebar-item "Team" = chat (alle interne rollen, ook mobiel); teambeheer heet nu "Teambeheer". Klanten kunnen nergens bij (§11: klantberichten gescheiden). Nog open: voice notes als audio, portaal-Berichten-tab.
9. **Opleverrapport** (§13): automatisch PDF met foto-bewijs, restpunten, handtekeningen.
10. Hardening: rechten per veld, notificatie-rate-limiting, soft deletes waar relevant.

## Huisstijl v2 (mockups §18)

- **Kleuren:** oranje (primair/CTA) `#F85B0B` = `brand-500`; navy `#070724` = `navy-950` (sidebar) en `#0B0B2B` = `navy-900`; statuskleuren groen/oranje/rood/grijs. Tokens in [app.css](resources/css/app.css). Staalblauw (`steel-*`) is legacy en wordt uitgefaseerd.
- **Look:** witte kaarten (`rounded-2xl border-gray-200`), veel witruimte, sidebar met iconen + oranje actieve pill, topbar met zoekbalk + "+ Nieuw", projectkaarten met foto (omslagfoto uploadbaar in het projectformulier, gestreamd via route `projects.cover` uit private storage — geen storage:link nodig; foto-bewijs kan de omslag later automatisch aanleveren), voortgangsbalken, avatar-stacks.
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

## Modules

**Offertes staat aan** (code-default `true` sinds de offerte-editor-sprint; uitzetten kan met `MODULE_QUOTES=false`). **Automations staat uit** (code-default `false`) tot de Nova-rules-sprint; aanzetten via `MODULE_AUTOMATIONS=true` + caches verversen. Uit = geen routes, geen menu, geen signalen. In tests staan beide aan (phpunit.xml).

## Automations & Aandacht (gebouwd, fundament voor Nova-rules §12)

- **Automations** ([app/Automations](app/Automations)): interface `Automation` + `AutomationRegistry::CLASSES`; idempotent via `AutomationRun::claim()`. Draait via `php artisan automations:run`, elke 15 min gescheduled ([routes/console.php](routes/console.php)) — scheduler moet draaien (`schedule:work` lokaal / cron live). Wordt later uitgebreid met per-regel modus: signaleren / bevestigen / automatisch.
- **Aandacht** ([AttentionService](app/Services/AttentionService.php)): alleen uitzonderingen; badge-teller 5 min gecachet (`attention-count`).
