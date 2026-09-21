# Renovion Dash

CRM + projectmanagementsysteem voor **Renovion** (renovatiebedrijf, Arnhem — stucwerk, schilderwerk, tegelwerk, badkamerrenovaties, complete renovaties). Gebouwd voor Imad en Raphael. Volledige functionele briefing: [technische-briefing.txt](technische-briefing.txt) — lees die bij twijfel over functionele scope.

## Leidend principe

> Imad en Raphael hoeven niet te onthouden wat er moet gebeuren. Het systeem laat zelf zien wat vandaag aandacht vraagt en is vanaf de telefoon met één tik of voice af te handelen.

- **Mobile-first, geen uitzonderingen.** Belangrijkste workflows worden voor mobiel ontworpen, desktop is de uitbreiding — niet andersom.
- **Actie gaat vóór informatie.** Geen dashboards vol statistieken; alleen wat een beslissing of actie vraagt. De Aandacht-module toont uitsluitend uitzonderingen.
- **Nova (AI-assistent) stelt voor, de gebruiker beslist.** Nova voert nooit belangrijke acties uit zonder bevestiging.

## Domein (kern)

- **Flow:** Sales → Uitvoering → Nazorg, in één omgeving.
- **Leadpipeline:** Nieuw → Contact → Intake → Offerte → Akkoord → Project (+ Verloren / On hold). Kanban- én lijstweergave.
- **Offertestatus:** Concept → Verstuurd → Bekeken → Opvolgen → Akkoord → Afgewezen. Bij akkoord wordt lead automatisch project.
- **Projectstatus:** Voorbereiding → Gepland → Uitvoering → Oplevering → Afgerond. "Afgerond" triggert de nazorgflow (bedankje + reviewverzoek via WhatsApp).
- **Taakstatus:** Te doen → Bezig → Wacht op → Afgerond.
- **Klantdossier:** één chronologische timeline per klant (aanvragen, mails, gesprekken, notities, voice memo's, offertes, betalingen, projectupdates, WhatsApp).
- **Rollen:** Admin (alles), Sales (leads/afspraken/offertes/klantcontact), Projectleider (projecten/planning/taken/documenten), Vakman (alleen eigen projecten/taken/status — extreem simpele interface).
- **Automations:** modulair — trigger → voorwaarden → actie → evt. goedkeuring (zie briefing §22 voor de tabel).

## Nova-architectuur (verplicht patroon)

Nooit vrije AI-output direct database-acties laten uitvoeren. Altijd:

```
Voice/tekst → intent → gestructureerde JSON-action → validatie → permissions → preview/bevestiging → backend-actie → audit log
```

**Audit trail** bij elke belangrijke actie: wie → wat → wanneer → oude waarde → nieuwe waarde → bron (Handmatig / Voice / Nova / Automation / Website / WhatsApp).

## Branding (van renovion.nl)

- **Logo:** [public/images/renovion-logo.svg](public/images/renovion-logo.svg) — witte tekst + oranje huisje, dus alleen op donkere achtergrond gebruiken.
- **Kleuren:**
  - Oranje (primair/CTA): `#f58220`, logo-oranje `#eb5d16`
  - Donker navy (headers/donkere vlakken): `#0a0a28`
  - Staalblauw (links/accenten): `#4e79a7`
  - Grijs (tekst): `#5a5c5b` / `#3a3c3b`
- **Font:** Inter Tight (Google Fonts).
- **Tone of voice:** professioneel maar toegankelijk, Nederlands, "geen half werk en vage beloftes". Alle UI-teksten in het Nederlands.
- **Geen emoji's in de UI** (keuze van Raphael): gebruik de `<x-icon name="...">`-component (heroicons outline, [icon.blade.php](resources/views/components/icon.blade.php)) en `<x-signal-dot color="red|amber|green">` voor statussignalen.
- Bedrijfsgegevens: Mercatorweg 28, 6827 DC Arnhem · info@renovion.nl · +31 6 395 353 00 · KVK 95384782.

## Stack & omgeving

- Laravel 13 · PHP 8.5 · Tailwind CSS 4 · Vite 8 · MySQL (`renovionapp`) · Laragon, URL: `https://renovionapp.test`
- **Frontend: Blade + Tailwind CSS + Alpine.js** — bewuste keuze van Raphael, géén Livewire/Inertia/Filament. Interactiviteit (Kanban, timeline, modals) met Alpine.js en waar nodig fetch naar JSON-endpoints.
- Queue/cache/sessies: database-driver.
- Basis: verse Laravel-skeleton; herbruikbare concepten uit EasyDash/EazyOnline (offertetool) waar mogelijk overnemen — zie zustermappen in `c:\laragon\www\`.

## Nova (AI-assistent) — status

- **Werkend sinds fase 1.5:** [NovaAssistant](app/Services/NovaAssistant.php) gebruikt de officiële `anthropic-ai/sdk` (PHP) met het model uit `RENOVION_AI_MODEL` (nu `claude-sonnet-5`) en `ANTHROPIC_API_KEY` uit `.env`.
- Flow volgt briefing §24 strikt: tekst/voice → tool-use intent → gevalideerde JSON-actie → preview → **gebruiker bevestigt** → uitvoeren → audit log (bron `nova` of `voice`). Nova voert nooit iets uit zonder bevestiging.
- Acties v1: `create_task`, `create_appointment`, `create_note`. Nieuwe acties: tool toevoegen in `tools()`, validatieregels in `validator()`, preview + execute-methode, en de actie aan `ACTIONS` toevoegen.
- Voice-invoer draait op de browser Web Speech API (nl-NL, Chrome); geen server-side speech-to-text nodig in deze fase.
- Endpoints: `POST /nova` (propose, throttled) en `POST /nova/uitvoeren` (execute). UI: chat-sheet achter de vaste Nova-knop in [app.blade.php](resources/views/components/layouts/app.blade.php) + `Alpine.data('nova')` in [app.js](resources/js/app.js).

## Tijdelijk uitgeschakelde modules

**Offertes en Automations staan nu uit** (keuze van Raphael, sept 2026: de app smal houden zodat Imad en Raphael overzicht houden). **De code-default is uit**; aanzetten kan alleen expliciet via `.env`: `MODULE_QUOTES=true` / `MODULE_AUTOMATIONS=true` (config `renovion.modules.*`) + caches verversen (`php artisan config:clear` en, indien gecachet, `config:cache && route:cache`). Uit betekent: geen routes, geen menu-items, geen offerte-signalen in dashboard/Aandacht/leadcards, geen automations-schedule. In tests staan beide altijd aan (phpunit.xml).

## Automations & Aandacht (fase 2 — gebouwd)

- **Automations** ([app/Automations](app/Automations)): interface `Automation` + `AutomationRegistry`; idempotent via `AutomationRun::claim()` (unieke run per automation+subject). Draaien via `php artisan automations:run`, gescheduled elke 15 min in [routes/console.php](routes/console.php) — **de scheduler moet wel draaien** (`php artisan schedule:work` lokaal / cron op de server). Nieuwe automation: class toevoegen + registreren in `AutomationRegistry::CLASSES`.
- Actief: telefoonnummer-opvragen (§9, met e-mail via `RequestPhoneNumberMail`), salestaak bij nieuwe lead, offerte-opvolging (3/5 dagen), intake-reminder, aanbetaling-waarschuwing. Offerte-akkoord→project zit in `AcceptQuote`.
- **Aandacht** ([AttentionService](app/Services/AttentionService.php)): alleen uitzonderingen (uitloop, offertes, aanbetalingen, planningconflicten incl. dubbele boekingen, verlopen deadlines). Badge-teller gecachet 5 min (`attention-count`).
- **Nova-dagbriefing**: `NovaAssistant::dailyBriefing()`, per gebruiker per dag 3 uur gecachet (`nova-briefing.{user}.{datum}`), async geladen op het dashboard via `GET /nova/briefing` (met `?refresh=1`).
- **Rechten (§25):** Gate `manage-crm` (alle rollen behalve vakman). Vakman: eigen dashboard ([dashboard/vakman](resources/views/dashboard/vakman.blade.php)), alleen eigen projecten/taken/planning, geen Nova/CRM. Testfactory-default is admin; de databasedefault blijft vakman.

## Faseringsvolgorde (niet vooruitlopen)

1. **Fase 1 (MVP):** mobiel dashboard ("Vandaag"), leads/CRM (Kanban + lijst), klantdossier/timeline, taken, planning, offertes, projectstatus. ✔ gebouwd
2. **Fase 2:** Nova-commands + voice ✔, Nova-dagbriefing ✔, automations ✔, Aandacht-module ✔, rechten ✔. Nog open: voice memo/transcriptie in het klantdossier.
3. **Fase 3:** WhatsApp-integratie, vakmanstatus, capaciteitsplanning met Nova-opties, reviewflow, AI-projectrisico's, inzichten.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
