import type { CapacitorConfig } from '@capacitor/cli';

// De schermen zitten in de app zelf (mobile/app, gebouwd naar mobile/www); de server levert de gegevens
// (api/app/*) en de schermen die nog niet in de app zitten, die openen in dezelfde webview. Het adres
// van de server gaat mee in de build van de schermen én in deze config, dus beide met dezelfde waarde:
//   RENOVION_API_URL=http://10.0.2.2:8000 npm run app:build && npx cap copy android
const serverUrl = process.env.RENOVION_API_URL ?? 'https://dash.renovion.nl';
const insecure = serverUrl.startsWith('http://');
// Een technisch Plesk-domein (*.plesk.page) toont elke browser eerst een waarschuwingspagina (428) met een
// "Continue"-knop; de app kan die niet wegklikken. Zonder "Mozilla" in de user agent komt hij er niet. Laravel
// herkent de app nog steeds aan "RenovionApp". Alleen voor testbuilds tegen zo'n domein.
const technicalDomain = new URL(serverUrl).hostname.endsWith('.plesk.page');

const config: CapacitorConfig = {
    appId: 'nl.renovion.dash',
    appName: 'Renovion OS',
    webDir: 'mobile/www',
    backgroundColor: '#0D1A2E',
    // Laravel herkent de app hieraan (bijv. "Ingelogd blijven" standaard aan).
    appendUserAgent: 'RenovionApp',
    ...(technicalDomain ? { overrideUserAgent: 'RenovionApp (testserver)' } : {}),
    server: {
        // http://localhost bij een lokale testserver over http, anders blokkeert de webview de API (gemengde inhoud).
        androidScheme: insecure ? 'http' : 'https',
        cleartext: insecure,
        // Alleen de hostnaam: Capacitor vergelijkt zonder poort. Met poort ("127.0.0.1:8000") herkent hij
        // de server niet en beantwoordt hij elk verzoek zelf met index.html.
        allowNavigation: [new URL(serverUrl).hostname],
        errorPath: 'offline.html',
    },
    ios: {
        contentInset: 'never',
        scheme: 'App',
    },
    plugins: {
        // Geen echte plugin: MainActivity leest hier het serveradres uit, om de Capacitor-brug ook op de
        // serverpagina's te zetten (Capacitor doet dat zelf alleen op het adres van de app, https://localhost).
        RenovionServer: {
            url: serverUrl,
        },
        SplashScreen: {
            // native.js verbergt de splash zodra de eerste pagina staat (of offline.html).
            launchAutoHide: false,
            backgroundColor: '#0D1A2E',
            showSpinner: false,
        },
        LocalNotifications: {
            // Wit huismerk in de Android-meldingenbalk (npm run mobile:assets), oranje accent.
            smallIcon: 'ic_stat_renovion',
            iconColor: '#F85B0B',
        },
        FirebaseMessaging: {
            // iOS: geen melding als de app open staat (Android doet dat vanzelf niet). De testmelding
            // uit Instellingen > Meldingen toont native.js wel, via LocalNotifications.
            presentationOptions: [],
        },
    },
    // Voorkomt een Swift Package-naamconflict van @capacitor-firebase/messaging (zie de README van de plugin).
    experimental: {
        ios: {
            spm: {
                packageOptions: {
                    '@capacitor-firebase/messaging': { symlink: true },
                },
            },
        },
    },
};

export default config;
