# Renovion OS

Bedrijfssysteem voor **Renovion Groep** (renovatiebedrijf, Arnhem: stucwerk, schilderwerk, tegelwerk, badkamers, complete renovaties). Gebruikers: Imad en Raphael (directie), het team, en via een portaal klanten en onderaannemers.

## De app is het prototype

Sinds 20 november 2026 (branch `renovion-os`) is **[renovion-os_32.html](renovion-os_32.html) het systeem**: de schermen, rekenregels en werkwijze van dat prototype draaien letterlijk in de app. De oude Laravel-schermen, modellen en modules zijn weggehaald. De stack bleef: **Laravel 13 + MySQL** op de server, Blade voor de schil en het inloggen, Vite voor de build (Tailwind en Alpine zijn beschikbaar voor Blade, de schermen zelf gebruiken de CSS van het prototype).

Bij twijfel over hoe iets hoort te werken of eruit hoort te zien: het prototype. De briefings (PDF's in de root) zijn achtergrond.

### Waar alles staat

| Wat | Waar |
| --- | --- |
| Schermen en logica (het prototype, aangepast) | [resources/js/os/app.js](resources/js/os/app.js) |
| CSS van het prototype | [resources/css/os.css](resources/css/os.css) |
| Eigen aanvullingen (skeletons, paginawissel, rustiger beweging, inloggen, fixes) | [resources/css/shell.css](resources/css/shell.css) |
| Brug naar de server (laden, opslaan, bijwerken, bestanden) | [resources/js/os/bridge.js](resources/js/os/bridge.js) |
| Hoe de staat in regels wordt geknipt (gedeeld JS/PHP) | [resources/js/os/layout.json](resources/js/os/layout.json) |
| Begrotingstool (draait in een iframe, praat via `window.__osBeg`) | [public/os/begtool.html](public/os/begtool.html) |
| Afbeeldingen uit het prototype | `public/os/img/` |
| Pagina en inloggen | [resources/views/os.blade.php](resources/views/os.blade.php), [auth/login.blade.php](resources/views/auth/login.blade.php) |
| Server | [app/Os](app/Os), [app/Http/Controllers/Os](app/Http/Controllers/Os) |
| Lege beginstand | [database/os/factory.json](database/os/factory.json) (gemaakt met `build-factory.cjs` uit het prototype) |

### Hoe het werkt

- Het prototype werkt met één staat `S`. Die staat op de server in `os_rows`: één regel per project, klant, taak, enzovoort (`projects/PD-2026-0001`), plus `#order` voor de volgorde. Zo werken collega's tegelijk zonder elkaars werk te overschrijven. `layout.json` bepaalt de indeling; [Layout](app/Os/Layout.php) en `bridge.js` gebruiken hem allebei.
- **Echte adressen:** elk scherm heeft een eigen URL (`/projecten`, `/project-pd-2026-0001`, `/agents`); het prototype gebruikte `#projecten`. `route()` en `go()` in app.js werken met `history.pushState`, een catch-all route (`/{scherm}`) geeft de schil terug, en oude links met `#` worden bij het openen omgezet. `/login` en `/dashboard` uit de vorige app verwijzen door.
- `save()` in het prototype stuurt na 300 ms alleen de gewijzigde regels naar `POST /os/opslaan`. Elke 8 seconden (en bij terugkomen in het tabblad) haalt de app op wat collega's veranderden (`GET /os/wijzigingen`). Binnenkomende wijzigingen worden pas verwerkt als er geen venster open is en je niet in een veld typt, zodat niemand zijn werk onder zijn handen ziet verdwijnen. Bij twee gelijktijdige wijzigingen van hetzelfde stuk wint de laatste.
- `os_meta` is een teller met een vergrendeling: elke opslag krijgt een volgnummer, zodat geen scherm een wijziging overslaat. `os_changes` is het logboek (wie, welke paden, wanneer).
- **Foto's en bestanden:** de app zet elke grote `data:`-URL in de staat eerst om naar een bestand (`POST /os/bestanden`, privé-opslag, ontdubbeld op sha256) en bewaart alleen de link. Documenten die het prototype alleen bij naam bewaarde, worden bij het kiezen geüpload; de naam wordt in het scherm een link.
- **Per gebruiker** (`perUser` in layout.json): gelezen chatberichten en Academy-voortgang. Thema en animaties staan in de browser (`rn-theme`, `rn-motion`).
- **Automations** rekenen in de browser (zoals in het prototype). De server wijst één scherm tegelijk aan dat ze draait (`POST /os/beurt`, 90 seconden, verlengt zich), anders zouden ze dubbel vuren.
- **Leeg beginnen:** een leeg systeem krijgt bij de eerste aanroep de fabrieksstaat (alle structuur, prijslijst, mailsjablonen, automations, geen demodata) en de bestaande interne accounts als medewerkers. `php artisan os:install` doet dat los; `php artisan os:reset` wist alles en begint opnieuw (op productie alleen met `--force`).

### Mensen, accounts en rechten

- Rollen zoals in het prototype: Superadmin, Directie, Sales, Calculator, Projectleider, Werkvoorbereider, Inkoper, Administratie, Uitvoerder, Onderaannemer, Klant ([UserRole](app/Enums/UserRole.php); `admin` in de database = Superadmin). Wat elke rol in het menu ziet, regelt het prototype (`PERM`).
- Inloggen gaat met een Laravel-account; `users.person_id` wijst naar de persoon in de staat (`emps`, `extraUsers` of een partnerbedrijf in `subs`). [People](app/Os/People.php) houdt die gelijk: wie in Teambeheer of Workforce met een e-mailadres wordt toegevoegd, krijgt een account; het wachtwoord komt **één keer** in beeld (met knoppen om het per mail of WhatsApp door te sturen, want er is geen mailserver). Dat wachtwoord is tijdelijk (`users.must_change_password`): na het inloggen moet de gebruiker eerst zelf een eigen wachtwoord kiezen (`/wachtwoord-kiezen`, middleware `password.chosen`). Deactiveren sluit het account en beëindigt de sessies. "Nieuw wachtwoord" in Teambeheer is alleen voor directie; op de server doet `php artisan os:tijdelijk-wachtwoord {email*}` hetzelfde. Er staan geen wachtwoorden in de code (`TeamSeeder` maakt tijdelijke).
- Alleen Superadmin en Directie veranderen rol, status, e-mail of projectrechten van iemand; de server zet zulke velden anders terug.
- De rolwissel uit het prototype ("Bekijk het systeem als rol") is voor directie een voorbeeldweergave per tabblad (sessionStorage), geen echte rechtenwissel.
- **Klant** (extraUser met `scope` = project): krijgt van de server alleen het eigen project (zonder interne notities), eigen klantgegevens, facturen, afspraken, berichten, verzoeken, meldingen en werkbonfoto's. Mag alleen berichten en notities plaatsen, een melding doen, aanleveren, een afspraak bevestigen, akkoord geven op meerwerk en een verzonden offerte tekenen; prijzen en planning blijven zoals op de server. Regels: [ClientScope](app/Os/Portal/ClientScope.php).
- **Onderaannemer** (partnerbedrijf): ziet het eigen dossier, de prijsaanvragen waarvoor hij is uitgenodigd zonder budget en zonder prijzen van anderen, eigen werkbonnen en klussen, en van projecten alleen naam en adres. Levert documenten aan en biedt; het keurmerk geeft Renovion. Regels: [PartnerScope](app/Os/Portal/PartnerScope.php).
- Tests van al deze regels: [OsStateTest](tests/Feature/OsStateTest.php).

### Bewuste afwijkingen van het prototype

- **Echte gebruiker en datum:** overal waar het prototype "Raphael" of de demodatum 30 oktober 2026 vast had, staat nu de ingelogde gebruiker (`ME`, `myName()`) en de echte datum; nummers (PD-, REN-, F-, WB-, BEG- …) gebruiken het lopende jaar. Taken die het prototype aan Joris, Fatima of Klaas gaf, gaan naar de eerste medewerker met die rol (`osSales()`, `osWvb()`, `osPL()` …), anders naar jou.
- **Geen demodata:** de demo-migraties (`ensureR2` en verder) draaien niet meer bij het opstarten; `osNormalize()` vult de standaardvelden die zij aanvulden. Het tabblad Demoflow (agentflow met een fictief project) is weg; agents, goedkeuringen en audit blijven. Gesimuleerde deelnemers in videobellen en het gescripte vergaderverslag zijn uitgezet; spraakherkenning met je eigen microfoon werkt.
- **Fouten uit het prototype hersteld:** knoppen die een latere bouwronde stilletjes onderschepte (klus `kl-*`, Mailbuilder `mb-*`, minderwerk `mnw-*`, agent pauzeren `agt-pause`), het formulier "Gebruiker uitnodigen" dat botste met "Partner uitnodigen" (`uinvite`), de marge van 8 pixels rond de app, lege toestanden op schermen die van demodata uitgingen.
- **Rustiger beweging:** geen waas, kanteling, waaier of uitrekkende zoom; blokken komen kort binnen, paginawissels gaan via View Transitions, laden toont skeletons in de vorm van het scherm. Animaties uit te zetten in het gebruikersmenu (of Alt+M).
- **Partners** krijgen een account via "Partner aanmelden" (de aanmeldlink is de inlogpagina) en een documentenlijst om aan te vullen.
- **Geen demo-teksten:** "demo-outbox" heet outbox; er gaat niets de deur uit zolang er geen mailserver is.

### Een nieuwe versie van het prototype

Er is geen automatische samenvoeging: `app.js` is het prototype plus de aanpassingen hierboven. Bij een nieuwe versie van Raphael: zoek de verschillen tussen de oude en nieuwe HTML en neem die per scherm over in `app.js`/`os.css`. Een nieuwe begrotingstool: vervang `public/os/begtool.html` en zet de drie kleine aanpassingen terug (status "Opgeslagen in het dossier", geen `initShared()` als hij ingebed is).

## Nova en de agents (echte AI)

Claude via de officiële PHP-SDK (`anthropic-ai/sdk`), sleutel `ANTHROPIC_API_KEY`, model `RENOVION_AI_MODEL` (live en lokaal: `claude-sonnet-5`, gekozen door de gebruiker; zonder die regel `claude-opus-5-5`). Zonder sleutel zeggen de schermen dat eerlijk en valt Nova terug op de herkenner van het prototype. Alles in [app/Os/Ai](app/Os/Ai).

- **Lezen:** [ReadTools](app/Os/Ai/ReadTools.php) (`zoeken`, `dossier`, `overzicht`) op [StateReader](app/Os/Ai/StateReader.php), altijd met de rechten van de gebruiker.
- **Voorstellen en uitvoeren:** [Actions](app/Os/Ai/Actions.php) kent taak, taak bijwerken, afspraak, notitie, contactmoment, chatbericht, klantbericht, mail (outbox), inplannen, planning wijzigen, begrotingsregel en aanvraag. `check()` controleert elk voorstel (bestaat het project, de persoon, de regel) en stuurt fouten terug naar het model; `execute()` schrijft via StateStore, dus met dezelfde rechten en hetzelfde logboek. Lege objecten blijven objecten (`shape()`).
- **Nova** ([Nova](app/Os/Ai/Nova.php), `POST /os/nova`): antwoord plus voorstelkaarten in het Nova-venster van het prototype. Voorstellen staan op de server (cache, een dag); "Bevestigen" voert precies dat voorstel uit (`POST /os/nova/{id}/bevestigen`). Werkwijze uit Instellingen: alleen adviseren, bevestigen, of veilige interne acties direct.
- **Agents** ([Agents](app/Os/Ai/Agents.php), [Signals](app/Os/Ai/Signals.php)): `php artisan os:agents`, elk half uur op werkdagen 7 tot 19 uur (de scheduler moet draaien), of de knop "Agents nu laten werken" voor directie. Signals zoekt met vaste regels waar werk ligt; pas dan wordt Claude aangeroepen, elk signaal één keer (`os_agent_runs`, met kosten). Het niveau per skill (Skills & SOP's) bepaalt: observeren = advies, voorbereiden = goedkeuring, protocol/begrensd = interne acties zelf uitvoeren, klantberichten en mail altijd via goedkeuring. Uitvoeringen, goedkeuringen en audit in dezelfde vorm als het prototype (`ai.runs`, `ai.approvals` met `action: nova_actions`, `ai.audit`). Goedkeuren controleert de rol op de server (`POST /os/agents/goedkeuringen/{id}`).
- **Papieren werkbon** ([WorkOrderReader](app/Os/Ai/WorkOrderReader.php)) leest een foto of pdf; **offertebuilder** ([OfferWriter](app/Os/Ai/OfferWriter.php)) schrijft, verkort of maakt teksten formeler of persoonlijker. Beide leveren een concept.
- Tests: [OsAiTest](tests/Feature/OsAiTest.php), zonder echte AI-aanroepen.

## Website en overstap

- **Websiteaanvragen:** `POST /api/aanvragen` met header `X-Intake-Token` (`LEAD_INTAKE_TOKEN`), zelfde velden als in de vorige app ([WebsiteLeadController](app/Http/Controllers/Api/WebsiteLeadController.php)). Wordt een klant (of bestaande klant op mailadres) met een dossier in fase Aanvraag en een taak om contact op te nemen; dubbel verstuurd binnen tien minuten telt één keer.
- **Overstap van de vorige app** (eenmalig, 23 november 2026 op live gedaan): `php artisan os:importeer-oud {klant-id's}` zet klanten met aanvragen en logboek over; `php artisan os:oude-app-opruimen --force` haalt daarna de oude tabellen weg.

## Werkafspraken

- Alle teksten Nederlands. Bedragen `€ 1.250,00`.
- Mobile-first: uitvoerders, klanten en partners werken op de telefoon.
- AI-uitvoer blijft altijd een voorstel dat een mens bevestigt (zie Nova en de agents).
- Controleer wijzigingen in een echte browser (headless Chrome via puppeteer-core): inloggen, het scherm openen, op console-fouten letten, en op telefoonbreedte (390) en desktop kijken. Lokaal stelt de app `window.__os` beschikbaar (`S`, `UI`, `render`) voor zulke tests; op productie niet.
- Bedrijfsgegevens: Mercatorweg 28, 6827 DC Arnhem, info@renovion.nl, +31 6 395 353 00, KVK 95384782.

## Stack en omgeving

- Laravel 13, PHP 8.5 lokaal (8.4 op live/Plesk), MySQL `renovionapp`, Vite 8, Laragon op `https://renovionapp.test`.
- Lokaal: `npm run build` (of `npm run dev`), `php artisan migrate`, `php artisan db:seed` (vier teamaccounts uit `TeamSeeder`, daarna de lege staat).
- Tests: `php artisan test` (sqlite in het geheugen; draai eerst `php artisan optimize:clear` als er een gecachte config staat).
- Live draait de scheduler via cron (`php artisan schedule:run` elke minuut); zonder cron werken de agents alleen op de knop.
- Live uitrollen: `git pull`/checkout, `php artisan migrate --force`, `public/build` vervangen door een verse `npm run build`, **altijd `php artisan optimize:clear`**. De oude tabellen van de vorige app blijven op live in de database staan maar worden niet meer gebruikt.
- Sessies, cache en queue: database. `SESSION_SECURE_COOKIE=true` hoort op live aan te staan.

