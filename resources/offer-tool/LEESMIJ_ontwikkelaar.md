# Renovion offerte in eigen systeem: opdracht voor de ontwikkelaar

Doel: klanten openen de offerte met **mobiel nummer + toegangscode**, standaard **7 dagen geldig**. De server controleert de code; zonder geldige code krijgt de klant de offerte (en de broncode met de begroting) nooit te zien.

## Bestanden

| Bestand | Wat |
| --- | --- |
| `Renovion_Offerte_systeem.html` | De tool (begroting + offerte). Werkt in team- of klantmodus via `window.RENOVION`. |
| `Renovion_Toegang.html` | Inlogpagina voor de klant (mobiel nummer + code). |
| `voorbeeld-server.js` | Werkend voorbeeld van dit contract in Node, alleen als referentie. Getest. |

## Hoe de codes werken

Het team maakt codes in de tool zelf (knop **Toegang**). De tool bewaart per persoon in `model.access.persons`:

```json
{ "id":"p…", "naam":"Hamid Reza", "rol":"klant|architect|overig", "codeOnly":false,
  "from":"2026-11-07T15:49", "until":"2026-11-14T15:49", "open":true,
  "salt":"…", "codeHash":"…" }
```

- De code zelf wordt **nergens opgeslagen**, alleen de hash: `sha256_hex(salt + "|" + tel + "|" + CODE)`.
  - `tel`: alleen cijfers; `0031…` en `31…` (11 cijfers) omzetten naar `0…`. Bij `codeOnly:true` (deelcode van 8 tekens) is `tel` leeg.
  - `CODE`: hoofdletters, alleen `0-9A-Z`.
- `from` en `until` zijn lokale tijd **Europe/Amsterdam** (`YYYY-MM-DDTHH:MM`; een datum zonder tijd betekent 00:00 resp. 23:59).
- Toegang is alleen geldig als `model.access.open`, `person.open` en nu tussen `from` en `until` ligt.

## Routes

**Team (achter jullie eigen login):**

1. `GET /team/offertes/{nr}`: lever `Renovion_Offerte_systeem.html` met direct na `<body>`:
   ```html
   <script>window.RENOVION={mode:"team",model:{…},rev:12,at:"…",
     saveUrl:"/api/offertes/{nr}",uploadUrl:"/api/offertes/{nr}/bestanden",
     clientUrl:"https://…/offerte/{nr}",csrf:"…"}</script>
   ```
2. `PUT /api/offertes/{nr}` body `{rev, model}`: opslaan als `rev` gelijk is aan de huidige, anders **409**. Antwoord `{rev, at}`. De tool slaat automatisch op (2,5 s na elke wijziging).
3. `POST /api/offertes/{nr}/bestanden` (multipart `file`, `id`): bewaar de bijlage, antwoord `{url}`. Die url moet ook voor ingelogde klanten opvraagbaar zijn.

**Klant:**

4. `GET /offerte/{nr}`:
   - geen geldige sessie: lever `Renovion_Toegang.html` met `<script>window.RENOVION_LOGIN={url:"/offerte/{nr}/toegang",project:"…",email:"info@renoviongroep.nl",csrf:"…"}</script>`
   - wel geldige sessie (en persoon nog steeds geldig): lever de tool met
     `window.RENOVION={mode:"klant",model:{…},rev,at,person:{id,naam,rol},signUrl:"/offerte/{nr}/akkoord",csrf:"…"}`.
     **Haal `model.access.persons` leeg** voordat je het model meestuurt.
5. `POST /offerte/{nr}/toegang` body `{tel, code}`:
   - goed: maak sessie (HttpOnly, Secure, SameSite=Lax cookie, verloopt uiterlijk op `until`), antwoord `200 {redirect:"/offerte/{nr}"}`
   - fout: `401 {error:"onbekend"}`, `403 {error:"verlopen",until}`, `403 {error:"nognniet",from}`, `403 {error:"dicht"}`, `429` bij te veel pogingen.
6. `POST /offerte/{nr}/akkoord` (alleen met geldige sessie) body `{persoon,naam,functie,plaats,datum,sig(PNG data-URL),nr,versie}`: opslaan en het team laten weten. Status op getekend zetten mag de server doen.

## Beveiliging: verplicht

- **Rate limiting** op `/toegang`: bijv. 5 pogingen per 15 minuten per IP én per offerte, daarna blokkeren. Een 6-cijferige code is anders te raden.
- Controleer de geldigheid **bij elk verzoek** (pagina, bijlagen, akkoord), niet alleen bij het inloggen: zo werkt dichtzetten of verlopen direct.
- Hash vergelijken in constante tijd; HTTPS verplicht; `Cache-Control: no-store` en `X-Robots-Tag: noindex` op klantpagina's.
- Log inlogpogingen en akkoorden (tijd, IP, persoon).

## Bekend punt

De tool bevat nu nog de voorbeeldgegevens van **kavel 4-15** in de broncode (standaardbegroting en uitgangspunten). Achter de login is dat veilig voor dit project. **Gebruik je hem voor andere projecten, laat die voorbeeldgegevens dan eerst verwijderen**: anders ziet een klant van een ander project ze in de broncode, en kunnen de uitgangspunten van kavel 4-15 in een andere offerte verschijnen.

## Getest

Met `voorbeeld-server.js`: team maakt code en slaat op naar de server, klant logt in, verkeerde en verlopen code worden geweigerd, broncode van de inlogpagina bevat geen offertegegevens, klantpagina bevat geen hashes, akkoord met handtekening komt binnen op de server.
