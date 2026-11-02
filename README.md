# Renovion Dash

CRM en projectmanagement voor Renovion (Arnhem): aanvraag, calculatie, offerte, project,
uitvoering met foto-bewijs, oplevering en nazorg. Laravel 13 met Blade, Tailwind en Alpine,
plus een iOS/Android-app (Capacitor) die de live omgeving laadt.

- Functionele en technische afspraken: [CLAUDE.md](CLAUDE.md)
- App bouwen en testen (iOS, Android): [mobile/README.md](mobile/README.md)

---

## Pushmeldingen naar productie (Firebase)

Pushmeldingen lopen via **Firebase Cloud Messaging**. Tijdens de ontwikkeling is het
**testproject `renovion-test-ccce9`** gebruikt. Voor de echte app moet er een eigen Firebase-project
van Renovion komen. Daarna zijn er drie dingen te vervangen:

| Bestand | Wat | Waar | Geheim? |
|---|---|---|---|
| `google-services.json` | Koppelt de Android-app aan Firebase | `android/app/` (in Git) | Nee |
| `GoogleService-Info.plist` | Koppelt de iOS-app aan Firebase | `ios/App/App/` (in Git) | Nee |
| Serviceaccount-sleutel (`.json`) | Laat de server meldingen versturen | Alleen op de server, **nooit in Git** | **Ja** |

De code hoeft niet te veranderen. Reken op ongeveer een uur, plus de tijd voor een nieuwe
app-versie in de stores.

### 1. Firebase-project aanmaken

1. Log in op https://console.firebase.google.com met een **Google-account van Renovion**
   (bijvoorbeeld info@renovion.nl), niet met een persoonlijk account.
2. Klik **Project maken**, noem het `Renovion` en **zet Google Analytics uit** (niet nodig voor push).
3. Voeg onder **Projectinstellingen > Gebruikers en rechten** Imad en Raphael toe als **Eigenaar**,
   zodat de toegang niet aan één persoon hangt.

### 2. Android-app toevoegen

1. Klik op het Android-icoon (robotje), of op **Projectinstellingen > Je apps > App toevoegen**.
2. **Android-pakketnaam:** `nl.renovion.dash`. Deze moet exact gelijk zijn aan `appId` in
   [capacitor.config.ts](capacitor.config.ts), anders faalt de Android-build. Laat het SHA-1-veld leeg.
3. Download **`google-services.json`** en vervang daarmee `android/app/google-services.json`.
   De overige stappen in de wizard (SDK, Gradle) kun je overslaan; dat staat al in het project.

### 3. iOS-app toevoegen

Vereist een **Apple Developer-account** van Renovion ($99 per jaar) en een Mac met Xcode.

1. **APNs-sleutel:** in het [Apple Developer-portaal](https://developer.apple.com/account/resources/authkeys/list)
   ga je naar *Keys > +*, vinkt **Apple Push Notifications service (APNs)** aan en download je het
   `.p8`-bestand. Noteer de **Key ID** en je **Team ID**. Het bestand is maar één keer te downloaden,
   dus bewaar het veilig (zelfde regels als de serviceaccount-sleutel).
2. **App ID:** registreer onder *Identifiers* het App ID `nl.renovion.dash` met **Push Notifications** aan.
3. **In Firebase:** voeg een iOS-app toe met bundle-ID `nl.renovion.dash` en download
   **`GoogleService-Info.plist`**.
4. Ga naar **Projectinstellingen > Cloud Messaging > Apple-appconfiguratie** en upload daar de `.p8`
   met Key ID en Team ID. Eén sleutel werkt zowel voor testbuilds als voor de App Store.
5. Zet `GoogleService-Info.plist` in `ios/App/App/` en voeg hem in Xcode toe aan het target **App**:
   sleep het bestand in de map App en vink *Add to targets: App* aan. Zet bij *Signing & Capabilities*
   **Push Notifications** en bij *Background Modes* **Remote notifications** aan.

### 4. Serviceaccount-sleutel op de live server

1. In Firebase: **Projectinstellingen > Serviceaccounts > Nieuwe privésleutel genereren**.
   Je krijgt een `.json`-bestand. Dit is een wachtwoord: niet mailen, niet in Git, niet in een chat.
2. Upload het op de live server (Plesk, Bestandsbeheer) naar
   `storage/app/private/firebase-credentials.json` in de Laravel-map. Die map is niet via het web
   bereikbaar en wordt door Git genegeerd. Zet de rechten op alleen-lezen voor de eigenaar (`600`).
3. Zet in de `.env` van de live server:

   ```
   FIREBASE_CREDENTIALS=storage/app/private/firebase-credentials.json
   ```

4. Ververs de caches, anders blijft de oude instelling actief:

   ```
   php artisan optimize:clear
   ```

5. Verwijder de sleutel daarna van je eigen pc (Downloads), en ook de sleutel van het testproject.

Staat `FIREBASE_CREDENTIALS` leeg, dan verstuurt de server geen push. De rest van de app werkt
gewoon door.

### 5. Nieuwe app-versie bouwen en uitbrengen

Het Firebase-project zit ín de app, dus gebruikers hebben een nieuwe versie nodig.

1. Synchroniseer **zonder** `CAP_SERVER_URL`, zodat de app naar de live omgeving wijst:

   ```
   npm ci
   npm run mobile:sync
   ```

2. Bouw de Android-app in Android Studio (`android/`) en de iOS-app in Xcode (`npm run mobile:open`).
   Verhoog het versienummer en upload naar Google Play en App Store/TestFlight.

Telefoons met de oude versie zijn nog aan het testproject gekoppeld. De server ruimt die oude
koppelingen automatisch op bij de eerste melding (Firebase antwoordt dan `SENDER_ID_MISMATCH`).
Zodra iemand de nieuwe versie opent, meldt de telefoon zich vanzelf opnieuw aan.

### 6. Controleren

1. Open de nieuwe app, log in en sta meldingen toe.
2. Ga naar **Meer > Meldingsinstellingen** en tik **Testmelding sturen**. Binnen een paar seconden
   verschijnt "Testmelding van Renovion".
3. Komt er niets? Kijk in `storage/logs/laravel.log` op de server naar **"Pushmelding mislukt"**.
   Veelvoorkomende oorzaken:

   | Melding of symptoom | Oorzaak |
   |---|---|
   | Geen push, geen foutmelding | `FIREBASE_CREDENTIALS` leeg of `optimize:clear` vergeten |
   | `SENDER_ID_MISMATCH` voor iedereen | Serviceaccount-sleutel en `google-services.json` komen uit verschillende projecten |
   | `cURL error 60` | PHP kent de vertrouwde certificaten niet (zie [mobile/README.md](mobile/README.md)) |
   | `PERMISSION_DENIED` zonder mismatch | De sleutel is ingetrokken of hoort bij een verwijderd project |
   | iPhone krijgt niets, Android wel | APNs-sleutel niet geüpload in Firebase of Push-capability uit in Xcode |

### 7. Testproject opruimen

Werkt alles op het nieuwe project, verwijder dan het testproject `renovion-test-ccce9` in de
Firebase-console (*Projectinstellingen > Algemeen > Project verwijderen*). Daarmee is de oude
sleutel ook ongeldig.

### Sleutel uitgelekt?

Trek hem direct in: Google Cloud Console > **IAM en beheer > Serviceaccounts** > het
`firebase-adminsdk`-account > **Sleutels** > verwijderen. Maak daarna een nieuwe sleutel aan
(stap 4). De app zelf hoeft daarvoor niet opnieuw gebouwd te worden.
