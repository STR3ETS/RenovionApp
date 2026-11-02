# Renovion-app voor iOS en Android (Capacitor)

De app is een native schil om de live Laravel-omgeving: alle schermen komen van de server, dus
een gewone `git pull` op productie verandert ook de app. Alleen wijzigingen in de native laag
(Capacitor-config, plugins, `ios/`, `android/`, icoon) vragen om een nieuwe build in de stores.

| Wat | Waar |
| --- | --- |
| Config (server-URL, bundle-ID, splash) | [`capacitor.config.ts`](../capacitor.config.ts) |
| Offline-scherm | [`mobile/www/offline.html`](www/offline.html) |
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
CAP_SERVER_URL=http://10.0.2.2:8000 npx cap sync android
```

Dan in Android Studio een emulator kiezen en Run. Op een echte telefoon in hetzelfde wifi-netwerk:
het IP van je pc gebruiken in plaats van `10.0.2.2`. Laragon (`https://renovionapp.test`) werkt niet
vanuit de emulator: die hostnaam bestaat daar niet en het certificaat is niet vertrouwd.

**Let op:** daarna weer `npm run mobile:sync` zonder variabele, anders gaat de lokale URL mee in een
release-build.

## Hoe Laravel de app herkent

De user agent eindigt op `RenovionApp`. Nu gebruikt: "Ingelogd blijven" staat in de app standaard aan.
In de browser zet [`native.js`](../resources/js/native.js) de class `native-app` op `<html>`.

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
