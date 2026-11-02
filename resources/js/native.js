// Native schil (Capacitor, iOS/Android). Capacitor injecteert de plugins vóór de pagina laadt
// als window.Capacitor.Plugins, dus hier is geen @capacitor/core nodig en blijft de web-bundle
// voor gewone browsers gelijk. Buiten de app doet dit bestand niets.
const cap = window.Capacitor;

export const isNativeApp = Boolean(cap?.isNativePlatform?.());

const plugin = (name) => (cap?.isPluginAvailable?.(name) ? cap.Plugins[name] : null);

// WKWebView heeft geen betrouwbare Web Speech API. Deze class bootst het stukje ervan na dat
// chat, calculatie en Nova gebruiken (lang, interimResults, onresult/onend/onerror, start/stop),
// zodat die componenten ongewijzigd werken met de native spraakherkenning.
class NativeSpeechRecognition {
    static SILENCE_MS = 2500;

    constructor() {
        this.lang = 'nl-NL';
        this.interimResults = false;
        this.onresult = null;
        this.onend = null;
        this.onerror = null;
        this.transcript = '';
        this.handles = [];
        this.silenceTimer = null;
        this.ended = false;
    }

    async start() {
        const speech = plugin('SpeechRecognition');

        try {
            let { speechRecognition } = await speech.checkPermissions();
            if (speechRecognition !== 'granted') {
                ({ speechRecognition } = await speech.requestPermissions());
            }
            if (speechRecognition !== 'granted') {
                return this.fail('not-allowed');
            }

            this.handles = [
                speech.addListener('partialResults', (event) => {
                    const text = event.matches?.[0];
                    if (!text) return;
                    this.transcript = text;
                    if (this.interimResults) this.emit(false);
                    // Browsers stoppen zelf na een stilte; native luistert door tot stop().
                    clearTimeout(this.silenceTimer);
                    this.silenceTimer = setTimeout(() => this.stop(), NativeSpeechRecognition.SILENCE_MS);
                }),
                speech.addListener('listeningState', (event) => {
                    if (event.state === 'stopped' || event.status === 'stopped') this.finish();
                }),
            ];

            await speech.start({ language: this.lang, partialResults: true, popup: false, addPunctuation: true });
        } catch (error) {
            this.fail(error?.message || 'audio-capture');
        }
    }

    stop() {
        clearTimeout(this.silenceTimer);
        plugin('SpeechRecognition')?.stop().catch(() => this.finish());
    }

    emit(isFinal) {
        const result = [{ transcript: this.transcript, confidence: 1 }];
        result.isFinal = isFinal;
        this.onresult?.({ results: [result], resultIndex: 0 });
    }

    finish() {
        if (this.ended) return;
        this.ended = true;
        clearTimeout(this.silenceTimer);
        this.handles.forEach((handle) => handle?.remove?.());
        if (this.transcript) this.emit(true);
        this.onend?.();
    }

    fail(error) {
        this.onerror?.({ error });
        this.finish();
    }
}

// Via een canvas, want Tailwind levert kleuren als oklch() en niet als rgb().
const colorProbe = Object.assign(document.createElement('canvas'), { width: 1, height: 1 }).getContext('2d', { willReadFrequently: true });

function toRgba(color) {
    colorProbe.clearRect(0, 0, 1, 1);
    colorProbe.fillStyle = color;
    colorProbe.fillRect(0, 0, 1, 1);
    return colorProbe.getImageData(0, 0, 1, 1).data;
}

// Statusbalk-tekst licht of donker, afhankelijk van wat er bovenaan het scherm staat
// (navy mobiele header en login, of de witte topbar op iPad).
function syncStatusBar() {
    const bars = plugin('SystemBars');
    if (!bars) return;

    let element = document.elementFromPoint(window.innerWidth / 2, 1);
    let rgba = [0, 0, 0, 0];
    while (element && rgba[3] < 128) {
        rgba = toRgba(getComputedStyle(element).backgroundColor);
        element = element.parentElement;
    }

    // Tijdens een paginaovergang is er nog niets te raken; dan niets aanpassen (anders wordt
    // "transparant zwart" als donker gelezen) en bij load/einde van de overgang opnieuw kijken.
    if (rgba[3] < 128) return;

    const [r, g, b] = rgba;
    const isDark = 0.299 * r + 0.587 * g + 0.114 * b < 140;
    bars.setStyle({ style: isDark ? 'DARK' : 'LIGHT' });
}

function openNotificationUrl(url) {
    if (typeof url === 'string' && url.startsWith('/') && !url.startsWith('//')) {
        window.location.href = url;
    }
}

// Pushmeldingen (Firebase): na inloggen het token van deze telefoon bij Laravel aanmelden.
// Opnieuw aanmelden alleen bij een ander token of andere gebruiker, of na een dag.
const PUSH_KEY = 'renovion-push';

function readPushRegistration() {
    try {
        return JSON.parse(localStorage.getItem(PUSH_KEY) ?? 'null');
    } catch {
        return null;
    }
}

async function registerPush(messaging) {
    const meta = document.querySelector('meta[name="push-register"]');
    if (!meta) return; // niet ingelogd of klantportaal

    let { receive } = await messaging.checkPermissions();
    if (receive !== 'granted') {
        ({ receive } = await messaging.requestPermissions());
    }
    if (receive !== 'granted') return;

    if (cap.getPlatform() === 'android') {
        await messaging.createChannel({ id: 'meldingen', name: 'Meldingen', description: 'Vermeldingen, taken en ondertekende offertes', importance: 4 });
    }

    const { token } = await messaging.getToken();
    const vorige = readPushRegistration();
    if (vorige?.token === token && vorige?.user === meta.dataset.user && Date.now() - vorige.at < 86_400_000) return;

    await window.postJson(meta.content, { token, platform: cap.getPlatform() });
    try {
        localStorage.setItem(PUSH_KEY, JSON.stringify({ token, user: meta.dataset.user, at: Date.now() }));
    } catch { /* geen opslag: volgende pagina probeert het opnieuw */ }
}

if (isNativeApp) {
    if (plugin('SpeechRecognition')) {
        window.SpeechRecognition = NativeSpeechRecognition;
    }

    // window.print() doet niets in WKWebView: native printdialoog (ook "Bewaar als PDF").
    const printer = plugin('RenovionPrint');
    if (printer) {
        window.print = () => printer.printPage({ title: document.title });
    }

    // target="_blank" opent Capacitor in Safari, waar de gebruiker niet is ingelogd.
    // Links binnen de eigen omgeving (bijv. chatbijlagen) blijven daarom in de app.
    document.addEventListener('click', (event) => {
        const link = event.target.closest?.('a[target="_blank"]');
        if (link && link.origin === window.location.origin) {
            event.preventDefault();
            window.location.href = link.href;
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        syncStatusBar();
        plugin('SplashScreen')?.hide({ fadeOutDuration: 200 });
    });
    window.addEventListener('load', syncStatusBar);
    window.addEventListener('resize', syncStatusBar);
    window.addEventListener('pageshow', syncStatusBar);
    window.addEventListener('renovion-top-changed', syncStatusBar); // bijv. de zwarte fotoviewer
    window.addEventListener('pagereveal', (event) => event.viewTransition?.finished.then(syncStatusBar));

    // Tikken op een melding (push of lokaal) opent waar die over gaat.
    plugin('LocalNotifications')?.addListener('localNotificationActionPerformed', ({ notification }) => openNotificationUrl(notification?.extra?.url));

    const messaging = plugin('FirebaseMessaging');
    if (messaging) {
        messaging.addListener('notificationActionPerformed', ({ notification }) => openNotificationUrl(notification?.data?.url));

        // Staat de app open, dan geen melding: wie in de app zit, ziet het daar al (Android toont dan
        // zelf niets, iOS niet door presentationOptions: [] in capacitor.config.ts). Alleen de
        // testmelding van Instellingen > Meldingen tonen we wel, die tik je juist in de app aan.
        messaging.addListener('notificationReceived', ({ notification }) => {
            // Geen pop-up, wel de teller op "Chat" in de bottom-nav meteen bijwerken (app-layout).
            window.dispatchEvent(new CustomEvent('renovion-push', { detail: notification.data ?? {} }));
            if (notification.data?.test !== '1') return;
            plugin('LocalNotifications')?.schedule({
                notifications: [{
                    id: Math.floor(Math.random() * 2 ** 31),
                    title: notification.title ?? 'Renovion',
                    body: notification.body ?? '',
                    largeBody: notification.body ?? '',
                    channelId: 'meldingen',
                    extra: { url: notification.data?.url },
                }],
            });
        });

        // Direct na installatie is Firebase soms nog niet klaar: dan na 5 seconden nog een keer.
        document.addEventListener('DOMContentLoaded', () => registerPush(messaging)
            .catch(() => setTimeout(() => registerPush(messaging).catch(() => {}), 5000)));

        // Uitloggen: token meesturen zodat deze telefoon geen meldingen meer krijgt voor deze gebruiker.
        document.addEventListener('submit', (event) => {
            if (!event.target.matches?.('form[action$="/logout"]')) return;
            const opgeslagen = readPushRegistration();
            if (!opgeslagen?.token) return;
            const input = Object.assign(document.createElement('input'), { type: 'hidden', name: 'push_token', value: opgeslagen.token });
            event.target.appendChild(input);
            try { localStorage.removeItem(PUSH_KEY); } catch { /* geen opslag */ }
        });
    }

    // Android: hardware-terugknop sluit eerst wat er bovenop ligt (sheet, Nova, foto; die roepen
    // preventDefault aan op "renovion-back"), en gaat anders terug in de geschiedenis in plaats
    // van de app te sluiten.
    plugin('App')?.addListener('backButton', ({ canGoBack }) => {
        const closedSomething = !window.dispatchEvent(new CustomEvent('renovion-back', { cancelable: true }));
        if (closedSomething) return;
        if (canGoBack) window.history.back();
        else plugin('App').exitApp();
    });

    document.documentElement.classList.add('native-app');
}
