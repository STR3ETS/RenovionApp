# Schermen in eigen bestanden

Elk scherm van de app dat niet in `index.html`/`main.js` staat, is hier een paar bestanden. `main.js` laadt ze
vanzelf (`import.meta.glob`): geen registratie in gedeelde bestanden nodig. Voorbeeld: `offerte.js` + `offerte.html`.

## `<naam>.js`

```js
export const routes = {
    screens: ['klussen'],                       // schermen zonder id: #/klussen
    details: { klus: /^\/klussen\/(\d+)$/ },    // pad op de server => detailscherm #/klus/5
    parents: { klus: 'klussen' },               // welk menu-item/tab oplicht
    sections: { klussen: 'uitvoering', klus: 'uitvoering' }, // sectiekleur (native-app.css, data-sec)
    paths: { '/klussen': 'klussen' },           // serverpad zonder id => scherm
    query: ['klus'],                            // schermen die een pad mét query overnemen: go('/klussen/5?tab=x') => #/klus/5?tab=x
    rewrites: [(pathname, params) => null],     // pad => andere hash, bijv. (p, q) => q.get('tab') === 'fotos' && p.match(/^\/projecten\/(\d+)$/) ? `#/fotos/${…}` : null};
// De query staat in $store.os.query (object), bijv. $store.os.query.aanvraag. Zonder 'query' opent een pad met
// query nog als serverpagina.

export default function register({ Alpine, api, screen, fetchScreen, fetchedAt, cache, errorText, animateIn, shrinkPhoto, format, novaUrl, printPart, quickToggles }) {
    Alpine.data('jobs', () => ({ ...screen('klussen', 'klussen') }));
    Alpine.data('job', (id) => ({ ...screen(`klus:${id}`, `klussen/${id}`) }));
}
```

- `screen(cacheNaam, apiPad)`: `data` (eerst uit de cache, dan vers), `failed`, `load({ force })`, `show(cacheNaam, pad)` voor filters.
- `printPart(element, titel)`: alleen dat element afdrukken of als PDF bewaren (printdialoog van de telefoon), zie `offertedocument.*`.
- `api(pad, { method, body })`: `api/app/<pad>` met token; gooit bij een fout (`error.status`, `error.data`). `errorText(error, 'Tekst. Controleer de verbinding.')` voor de toast.
- Na een wijziging: `fetchedAt.<cacheNaam> = 0` voor lijsten die het tonen, en `this.load({ force: true })`.
- **Meteen reageren:** een tik toont het resultaat direct en de server volgt; niet de hele knoppenrij op slot en geen
  melding bij succes, alleen bij een fout (dan springt het terug). Afvinken: `...quickToggles({ locate, read, write,
  send, refresh, fallback })` en `this.quickToggle(item)` (voorbeelden: `kwaliteit.js`, `klussen.js`). Een keuze
  (status): zet hem lokaal, sla op, bij een fout terug (`uitvoering.js` `setStatus`, `deferredStatus` in main.js).
  Let op: `:disabled="... || saving[id]"` geeft `undefined` als er niets loopt, en Alpine maakt de knop dan dicht;
  schrijf `Boolean(saving[id])`.
- Schermwissels schuiven vanzelf (vooruit van rechts, terug van links, tabs overvloeien) en terug komt op dezelfde
  scrollplek uit (`visit`, `restoreScroll`, `slideTo` in main.js); een scherm hoeft daar niets voor te doen.
- Store (`Alpine.store('os')` of `$store.os` in de template): `go(serverpad)` (naar het scherm in de app als dat er is, anders de serverpagina), `openWeb(serverpad)` (altijd de serverpagina, bijv. de offerte-builder), `toast(tekst)`, `ask(vraag, knop)` (Promise<bool>), `crm` (kantoor), `profile`, `param` (id van een detailscherm), `route`.
- `shrinkPhoto(file)`: foto verkleinen vóór uploaden (zoals werkpakketten en chat).

## `<naam>.html`

Eén of meer `<template x-if="$store.os.route === '<scherm>'">`, zoals de schermen in `index.html`. Detailscherm:

```html
<template x-if="$store.os.route === 'klus'">
    <div>
        <template x-for="id in [$store.os.param]" :key="id">
            <div x-data="job(id)">…</div>
        </template>
    </div>
</template>
```

Bouwstenen (native-app.css), dezelfde als de bestaande schermen: `app-crumbs`, `app-ph` (kop met `sec-eye`, `h1`,
`sub`, `app-ph-acts`), `app-card` (+ `app-card-h`/`app-card-b`), `app-li`, `app-ic` (+ `or`/`ok`/`blue`/`bad`/`vio`),
`app-fields` met `app-lbl`, `app-btn` (+ `pri`, `ghost`, `soft`), `app-chip`, `app-kpi`, `app-bar`, `skeleton`, `data-enter`.
Iconen: `<span x-icon="'naam'" class="h-4 w-4"></span>` (namen uit `resources/views/components/icon.blade.php`).
Statuskleuren uit `appBadgeClasses()` van de enum (lichte badges). Gebruik de lichte Tailwind-kleuren (`gray-*`,
`navy-*`, `brand-*`, `green-*`, `amber-*`, `red-*`): de donkere weergave zet die zelf om. Nooit `surface-*`/`slate-*`
(dat is de donkere website). Laden: skeletons zolang `data` er niet is; `failed` geeft "Geen verbinding" met opnieuw proberen.
Alles moet passen op 360-430 px breed, knoppen minstens 44 px hoog.

## Server

- Gegevens: `app/Http/Controllers/Api/App/<Naam>Controller.php`, JSON in de vorm die het scherm nodig heeft (paden
  met `route(..., false)`, bedragen als getal, datums ISO of al opgemaakt).
- Routes: `routes/app/<naam>.php`, vanzelf geladen binnen de groep voor intern personeel (prefix `app/`, namen
  `api.app.*`). Kantoor: `Route::middleware('can:manage-crm')->group(...)`. Zie `routes/app/offertes.php`.
- Wijzigingen via dezelfde controllers als de website waar dat kan (zelfde validatie en regels): die krijgen een
  JSON-antwoord bij `$request->expectsJson()` (zie `QuoteStatusController`).
- Afbeeldingen voor `<img>`: ondertekend relatief adres (`URL::temporarySignedRoute(..., absolute: false)`), de app heeft geen sessiecookie.
- Tests: `tests/Feature/App<Naam>Test.php` met `$user->createToken('test')->plainTextToken` en `withToken()`.
