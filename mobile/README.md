# Renovion-app voor iOS en Android (Capacitor)

De belangrijkste schermen zitten in de app zelf ([`mobile/app/`](app)); de server levert alleen de
gegevens (`api/app/*`) en de schermen die nog niet in de app zitten, die openen als serverpagina in
dezelfde app. Een wijziging aan een ingebouwd scherm vraagt dus een nieuwe build in de stores; een
wijziging aan de gegevens of aan een serverpagina gaat live met een gewone deploy.

```bash
RENOVION_API_URL=https://dash.renovion.nl npm run app:build   # schermen naar mobile/www
RENOVION_API_URL=https://dash.renovion.nl npx cap sync         # zelfde adres in de native config
```

| Wat | Waar |
| --- | --- |
| Config (server-URL, bundle-ID, splash) | [`capacitor.config.ts`](../capacitor.config.ts) |
| Offline-scherm | [`mobile/app/public/offline.html`](app/public/offline.html) (gaat mee in `npm run app:build`) |
| JS-brug (spraak, printen, statusbalk, links) | [`resources/js/native.js`](../resources/js/native.js) |
| Native code iOS (swipe-terug, printplugin, offline-filter) | [`ios/App/App/MainViewController.swift`](../ios/App/App/MainViewController.swift) |
| Native code Android (printplugin, offline-filter) | [`android/app/src/main/java/nl/renovion/dash/`](../android/app/src/main/java/nl/renovion/dash/) |
| Toestemmingsteksten (camera, microfoon, foto's, spraak) | [`ios/App/App/Info.plist`](../ios/App/App/Info.plist) |
| Icoon + splash genereren | `npm run mobile:assets` ([`make-assets.mjs`](make-assets.mjs)) |

## iOS bouwen (Mac met Xcode 16+)

iOS bouwen kan alleen op macOS.

```bash
npm ci
npm run mobile:sync      # schrijft capacitor.config.json + offline.html in ios/ en android/
npm run mobile:open      # opent Xcode
```

In Xcode: target **App** > *Signing & Capabilities* > kies het Apple Developer-team. Draaien op een
iPhone of simulator met Run; voor TestFlight/App Store: *Product > Archive* > *Distribute App*.
Verhoog per upload `CURRENT_PROJECT_VERSION` (build) en bij een release `MARKETING_VERSION`.

## Android testen (Windows, Android Studio)

Android Studio staat hier op `D:\Android\Android Studio`; `cap open android` zoekt standaard op C:, dus
eenmalig `setx CAPACITOR_ANDROID_STUDIO_PATH "D:\Android\Android Studio\bin\studio64.exe"` (of open de
map `android/` gewoon zelf in Android Studio). Gradle heeft JDK 21 nodig: in Android Studio
*Settings > Build Tools > Gradle > Gradle JDK* op de meegeleverde JDK (of `D:\Android\jdk-21`).

Testen tegen een lokale Laravel in de emulator (`10.0.2.2` is de pc vanuit de emulator):

```bash
php artisan serve --host=0.0.0.0 --port=8000         # met npm run build gedaan en zonder public/hot
RENOVION_API_URL=http://10.0.2.2:8000 npm run app:build
RENOVION_API_URL=http://10.0.2.2:8000 npx cap sync android
```

Dan in Android Studio een emulator kiezen en Run. Op een echte telefoon zonder kabel: het wifi-adres
van je pc gebruiken (bijv. `RENOVION_API_URL=http://192.168.1.96:8000`, zie `ipconfig`); telefoon en pc
moeten op hetzelfde wifi-netwerk zitten, en verandert het adres van de pc, dan opnieuw bouwen. Vast
adres: een DHCP-reservering voor de pc in de router. Op een echte telefoon aan de USB-kabel:
`adb reverse tcp:8000 tcp:8000` en `http://127.0.0.1:8000` als adres (werkt op elk wifi-netwerk, maar
alleen zolang de kabel erin zit en de reverse actief is). Laragon (`https://renovionapp.test`) werkt
niet vanuit de emulator: die hostnaam bestaat daar niet en het certificaat is niet vertrouwd.

**Let op:** daarna beide stappen opnieuw zonder variabele, anders gaat de lokale URL mee in een
release-build.

## Hoe Laravel de app herkent

De user agent eindigt op `RenovionApp`. Daarmee krijgt de app de Renovion OS-stijl en een eigen schil, en staat
"Ingelogd blijven" standaard aan; de website blijft zoals hij is, ook in een telefoonbrowser. De server zet de
class `native-app` op `<html>` (`App\Support\NativeApp`, `@nativeapp` in Blade, `app:` in Tailwind), en
[`native.js`](../resources/js/native.js) doet dat nog een keer in de app zelf.

## Verschillen tussen de platforms

- **Terug:** iOS swipe vanaf de rand, Android de systeem-terugknop/-gebaar (via `native.js`).
- **Offline-scherm:** op Android geeft [`RenovionWebViewClient`](../android/app/src/main/java/nl/renovion/dash/RenovionWebViewClient.java)
  de mislukte URL mee (`?retry=`), zodat "Opnieuw proberen" naar dezelfde pagina gaat. Capacitor toont op
  Android standaard ook bij HTTP-fouten (404, 419, 500) het offline-scherm; dat is daar uitgezet.
- **Camera:** Android vraagt geen camerarechten in het manifest; `<input capture>` opent de camera-app.

## Pushmeldingen testen

Nodig: `android/app/google-services.json` (in de repo) en de serviceaccount-sleutel van hetzelfde
Firebase-project buiten Git, met het pad in `.env`:

```
FIREBASE_CREDENTIALS=storage/app/private/firebase-credentials.json
```

1. App in de emulator, inloggen als Peter, meldingen toestaan. De app meldt de telefoon zelf aan.
   Snelste check: **Meer > Meldingsinstellingen > Testmelding sturen**.
2. In de browser inloggen als Imad en in Team > Algemeen `@Peter ...` sturen, of Peter een taak geven.
3. Binnen een paar seconden staat de melding op de emulator: met de app open (bovenin), op de
   achtergrond of weggeveegd. Tikken opent de juiste pagina.

**Windows: "cURL error 60: unable to get local issuer certificate"?** Dan weet PHP niet waar de
vertrouwde certificaten staan en kan Laravel Google niet bereiken. Download
https://curl.se/ca/cacert.pem en zet in `php.ini` `curl.cainfo` en `openssl.cafile` naar dat bestand
(met gewone slashes), daarna `php artisan serve` herstarten. Op de live server speelt dit normaal niet.
