import type { CapacitorConfig } from '@capacitor/cli';

// De app is een native schil om de live Laravel-omgeving: alle schermen komen van de
// server (Blade + Alpine), de native laag levert statusbalk, splash, spraak, printen,
// swipe-terug en een offline-scherm. Lokaal testen tegen een andere server:
//   CAP_SERVER_URL=http://192.168.1.20:8000 npx cap sync ios
const serverUrl = process.env.CAP_SERVER_URL ?? 'https://dash.renovion.nl';

const config: CapacitorConfig = {
    appId: 'nl.renovion.dash',
    appName: 'Renovion',
    webDir: 'mobile/www',
    backgroundColor: '#070724',
    // Laravel herkent de app hieraan (bijv. "Ingelogd blijven" standaard aan).
    appendUserAgent: 'RenovionApp',
    server: {
        url: serverUrl,
        cleartext: serverUrl.startsWith('http://'),
        errorPath: 'offline.html',
    },
    ios: {
        contentInset: 'never',
        scheme: 'App',
    },
    plugins: {
        SplashScreen: {
            // native.js verbergt de splash zodra de eerste pagina staat (of offline.html).
            launchAutoHide: false,
            backgroundColor: '#070724',
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
