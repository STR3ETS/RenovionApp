// Renovion OS-app: de schermen zitten in de app zelf, de server levert alleen gegevens (api/app/*).
// Schermen die hier nog niet zijn, opent de app op de server met een eenmalige inloglink (webcode).
import Alpine from 'alpinejs';
import './app.css';
import '../../resources/js/native.js';
import swipeDismiss from '../../resources/js/swipe-dismiss.js';
import { iconSvg } from './icons.js';
import heroUrl from '../../public/images/os-hero.jpg';
import novaUrl from '../../public/nova.jpg';

const API = import.meta.env.VITE_RENOVION_API;
const TOKEN_KEY = 'renovion-os-token';
const PROFILE_KEY = 'renovion-os-profile';
const SCREENS = ['vandaag', 'inbox', 'projecten', 'planning', 'taken'];
// Sectiekleur per scherm (native-app.css, data-sec)
const SECTIONS = { planning: 'uitvoering', taken: 'uitvoering', project: 'projecten', werkpakket: 'projecten' };
// Serverpaden die in de app een eigen scherm hebben (meldingen, inbox, menu)
const SCREEN_PATHS = { '/': 'vandaag', '/aandacht': 'inbox', '/projecten': 'projecten', '/planning': 'planning', '/taken': 'taken' };

// Laadscherm voor een serverpagina in de vorm van die pagina (index.html, "Naar een serverpagina"):
// chatlijst, gesprek, formulier, projectdossier, aanvragen, zoeken of een gewone lijst. tabs = de
// onderstreepte sectietabs bovenaan (AppSection op de server).
const SECTION_TABS = /^\/(aanvragen|klanten|offertes|calculaties|prijslijst|verslagen|automations|instellingen)(\/|$)/;
function leaveScreen(path) {
    const pathname = path.split('?')[0].replace(/\/+$/, '') || '/';
    const kind = pathname === '/chat' ? 'chats'
        : /^\/chat\/\d+$/.test(pathname) ? 'gesprek'
            : /\/(aanmaken|create|nieuw|bewerken|opnemen)$/.test(pathname) ? 'form'
                : /^\/projecten\/\d+$/.test(pathname) ? 'dossier'
                    : pathname === '/aanvragen' ? 'kanban'
                        : pathname === '/zoeken' ? 'zoeken'
                            : 'lijst';
    return { kind, tabs: kind === 'kanban' || (kind === 'lijst' && SECTION_TABS.test(pathname)) };
}

const capacitor = () => window.Capacitor?.Plugins ?? {};

const storage = {
    get: (key) => { try { return localStorage.getItem(key); } catch { return null; } },
    set: (key, value) => { try { localStorage.setItem(key, value); } catch { /* geen opslag */ } },
    remove: (key) => { try { localStorage.removeItem(key); } catch { /* geen opslag */ } },
};

// Het token staat ook in de native opslag (Preferences): iOS mag de opslag van de webview leegmaken als
// de telefoon vol raakt, en dan zou je zomaar uitgelogd zijn.
const secure = {
    set: (value) => capacitor().Preferences?.set({ key: TOKEN_KEY, value }).catch(() => {}),
    remove: () => capacitor().Preferences?.remove({ key: TOKEN_KEY }).catch(() => {}),
    get: async () => (await capacitor().Preferences?.get({ key: TOKEN_KEY }).catch(() => null))?.value ?? null,
};

class ApiError extends Error {
    constructor(status, data) {
        super(`HTTP ${status}`);
        this.status = status;
        this.data = data;
    }
}

// Buildnummer van deze app (Android versionCode, iOS CFBundleVersion; uit mobile/release.json). Gaat mee
// met elk verzoek: is de app ouder dan minimum_build, dan antwoordt de server met 426 (verplichte update).
const appBuild = Promise.resolve(capacitor().App?.getInfo?.())
    .then((info) => (info?.build ? String(parseInt(info.build, 10) || 0) : null))
    .catch(() => null);

async function api(path, { method = 'GET', body } = {}) {
    const token = storage.get(TOKEN_KEY);
    const build = await appBuild;
    // Foto's gaan als FormData: dan zet de browser zelf het Content-Type (met de multipart-grens).
    const form = body instanceof FormData;
    let response;
    try {
        response = await fetch(`${API}/api/app/${path}`, {
            method,
            headers: {
                Accept: 'application/json',
                ...(form ? {} : { 'Content-Type': 'application/json' }),
                ...(token ? { Authorization: `Bearer ${token}` } : {}),
                ...(build ? { 'X-App-Build': build } : {}),
            },
            body: form ? body : body ? JSON.stringify(body) : undefined,
        });
    } catch (error) {
        // Geen verbinding met de server: laten zien dat de gegevens van eerder zijn.
        Alpine.store('os').offline = true;
        throw error;
    }
    Alpine.store('os').offline = false;
    if (response.status === 401 && token) Alpine.store('os').forget();
    if (response.status === 426) {
        // Verplichte update: het hele scherm wordt "Update nodig" tot de nieuwe versie er is.
        const data = await response.json().catch(() => null);
        Alpine.store('os').update = data?.update ?? { url: null, reason: null };
        throw new ApiError(426, data);
    }
    if (!response.ok) throw new ApiError(response.status, await response.json().catch(() => null));
    return response.status === 204 ? null : response.json();
}

const routeFromHash = () => {
    const [route, param] = location.hash.replace(/^#\/?/, '').split('?')[0].split('/');
    if (['project', 'werkpakket'].includes(route) && /^\d+$/.test(param ?? '')) return { route, param };
    return { route: SCREENS.includes(route) || route === 'uitloggen' ? route : 'vandaag', param: null };
};

// Opmaak, zoals op de server (Tally, bedragen, datums)
const format = {
    tally: (count, one, many) => `${count} ${count === 1 ? one : many}`,
    euro: (value, decimals = 0) => `€ ${Number(value ?? 0).toLocaleString('nl-NL', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })}`,
    today: () => new Intl.DateTimeFormat('nl-NL', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date()),
    greeting: () => { const hour = new Date().getHours(); return hour < 12 ? 'Goedemorgen' : hour < 18 ? 'Goedemiddag' : 'Goedenavond'; },
    day: (iso) => {
        const date = new Date(`${iso}T12:00:00`);
        const today = new Date();
        const diff = Math.round((date.setHours(0, 0, 0, 0) - today.setHours(0, 0, 0, 0)) / 86_400_000);
        if (diff === 0) return 'Vandaag';
        if (diff === 1) return 'Morgen';
        return new Intl.DateTimeFormat('nl-NL', { weekday: 'short', day: 'numeric', month: 'short' }).format(new Date(`${iso}T12:00:00`));
    },
    time: (at) => new Intl.DateTimeFormat('nl-NL', { hour: '2-digit', minute: '2-digit' }).format(new Date(at)),
    since: (at) => {
        const date = new Date(at);
        return date.toDateString() === new Date().toDateString()
            ? `vandaag ${format.time(at)}`
            : new Intl.DateTimeFormat('nl-NL', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }).format(date);
    },
    initials: (name) => (name ?? '').split(' ').slice(0, 2).map((part) => part[0]?.toUpperCase() ?? '').join(''),
};

window.osFormat = format;

Alpine.store('os', {
    token: storage.get(TOKEN_KEY),
    profile: JSON.parse(storage.get(PROFILE_KEY) ?? 'null'),
    route: routeFromHash().route,
    param: routeFromHash().param,
    drawer: false,
    help: false,
    nova: false,
    leaving: null, // { kind, tabs } van de serverpagina die opent, zie leaveScreen()
    update: null, // { url, reason } als deze app te oud is (426), zie api() en checkVersion()
    offline: false,
    shownAt: null,
    toastText: null,
    question: null,
    heroUrl,
    novaUrl,
    // Openbare pagina op de server, dus zonder eenmalige code; ook vanaf het inlogscherm.
    privacyUrl: `${API}/privacy`,

    get signedIn() {
        return Boolean(this.token && this.profile);
    },
    get crm() {
        return Boolean(this.profile?.can?.crm);
    },
    get counts() {
        return this.profile?.counts ?? { attention: 0, chat: 0, notifications: 0 };
    },
    get menu() {
        return this.profile?.menu ?? { tabs: [], groups: [], plus: [] };
    },
    // Menu-item actief: het scherm zelf, of een scherm dat erbij hoort (dossier bij Projecten, Taken bij Planning).
    isActive(screen) {
        return Boolean(screen) && (screen === this.route
            || (['project', 'werkpakket'].includes(this.route) && screen === 'projecten')
            || (this.route === 'taken' && screen === 'planning'));
    },

    init() {
        window.addEventListener('hashchange', () => this.navigate());
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState !== 'visible') return;
            this.checkVersion();
            this.refreshProfile().then(() => this.token && preload(this.crm));
        });
        window.addEventListener('renovion-push', () => this.refreshProfile());
        // Elke minuut de tellers; zonder verbinding elke 15 seconden kijken of de server er weer is.
        setInterval(() => document.visibilityState === 'visible' && !this.offline && this.refreshProfile(), 60_000);
        setInterval(() => this.offline && document.visibilityState === 'visible' && this.refreshProfile().then(() => !this.offline && preload(this.crm)), 15_000);
        window.addEventListener('renovion-back', (event) => {
            if (!this.drawer && !this.help && !this.nova && !this.question) return;
            if (this.question) this.answer(false);
            this.drawer = this.help = this.nova = false;
            event.preventDefault();
        });
        // Tik op een pushmelding (native.js): naar het scherm in de app, of anders de serverpagina.
        window.renovionOpenPath = (path) => this.go(path);
        // Terug van een serverpagina uit de terug-cache: het laadscherm van daarnet mag niet blijven staan.
        window.addEventListener('pageshow', (event) => { if (event.persisted) this.leaving = null; });
        this.navigate();
        // Pas na het laden van dit bestand: Alpine roept init() al aan bij het registreren van de store,
        // als fetchScreen en de cache verderop nog niet bestaan.
        setTimeout(() => { this.checkVersion(); this.restore(); });
    },

    // Mag deze app nog? Ook vóór het inloggen. Te oud = 426, en dan zet api() het scherm "Update nodig".
    checkVersion() {
        api('versie').then(() => { this.update = null; }).catch(() => { /* 426 of geen verbinding */ });
    },

    openUpdate() {
        if (this.update?.url) location.href = this.update.url;
    },

    // Na een herstart: het token terug uit de native opslag als de webview het kwijt is (iOS).
    async restore() {
        if (!this.token) {
            const token = await secure.get();
            if (token && this.profile) {
                this.token = token;
                storage.set(TOKEN_KEY, token);
            }
        }
        if (!this.token) return;
        // Bewaarde gegevens van vorige keer staan er al; nu alles vers, Vandaag eerst.
        preload(this.crm);
        await this.refreshProfile();
        this.registerPush();
    },

    navigate() {
        const { route, param } = routeFromHash();
        this.route = route;
        this.param = param;
        this.drawer = this.help = this.nova = false;
        if (route === 'uitloggen') {
            this.forget();
            return;
        }
        document.documentElement.dataset.sec = SECTIONS[route] ?? route;
        window.scrollTo(0, 0);
        setTimeout(() => window.dispatchEvent(new Event('renovion-top-changed')), 50);
    },

    // Een pad op de server: naar het scherm in de app als dat er is, anders de serverpagina.
    go(path) {
        const [pathname, query] = path.split('?');
        const project = pathname.match(/^\/projecten\/(\d+)$/);
        const workPackage = pathname.match(/^\/werkpakketten\/(\d+)$/);
        if (SCREEN_PATHS[pathname] && !query) location.hash = `#/${SCREEN_PATHS[pathname]}`;
        else if (project && !query) location.hash = `#/project/${project[1]}`;
        else if (workPackage) location.hash = `#/werkpakket/${workPackage[1]}`;
        else this.openWeb(path);
    },

    // Een menu-item uit api/app/ik (AppMenu op de server)
    open(item) {
        this.drawer = false;
        if (item.nova) this.nova = true;
        else if (item.screen) location.hash = `#/${item.screen}`;
        else this.openWeb(item.path);
    },

    remember(token, profile) {
        this.token = token;
        storage.set(TOKEN_KEY, token);
        secure.set(token);
        this.setProfile(profile);
        if (!profile.can.internal) {
            this.openWeb('/portaal');
            return;
        }
        preload(this.crm);
        this.registerPush();
    },

    setProfile(profile) {
        this.profile = profile;
        storage.set(PROFILE_KEY, JSON.stringify(profile));
    },

    // Pushmeldingen: native.js vraagt het token op en meldt het via de app-API aan.
    registerPush() {
        if (!this.signedIn || !this.profile.can.internal) return;
        window.renovionPush = { user: this.profile.user.id, register: (body) => api('apparaten', { method: 'POST', body }) };
        window.renovionRegisterPush?.();
    },

    forget() {
        cache.clear();
        this.token = null;
        this.profile = null;
        storage.remove(TOKEN_KEY);
        storage.remove(PROFILE_KEY);
        secure.remove();
        window.renovionPush = null;
        window.renovionForgetPush?.();
        // Ook de websessie van deze telefoon (serverpagina's) eindigt.
        capacitor().CapacitorCookies?.clearAllCookies().catch(() => {});
        if (location.hash !== '#/vandaag') history.replaceState(null, '', '#/vandaag');
        this.route = 'vandaag';
    },

    async refreshProfile() {
        if (!this.token) return;
        try {
            this.setProfile(await api('ik'));
        } catch { /* offline: de vorige gegevens blijven staan */ }
    },

    async logout() {
        if (!(await this.ask('Uitloggen op deze telefoon?', 'Uitloggen'))) return;
        try {
            await api('uitloggen', { method: 'POST', body: { push_token: window.renovionPushToken?.() } });
        } catch { /* offline: het token vervalt op de telefoon toch */ }
        this.forget();
    },

    // Een scherm dat nog niet in de app zit: de serverpagina openen, ingelogd via een eenmalige code.
    // Tot hij er is, staat er een laadscherm in de vorm van die pagina (leaveScreen).
    async openWeb(path) {
        this.leaving = leaveScreen(path);
        try {
            const { url } = await api('webcode', { method: 'POST', body: { naar: path, terug: location.origin } });
            location.href = url;
        } catch {
            this.leaving = null;
            this.toast('Geen verbinding. Probeer het zo nog eens.');
        }
    },

    toast(text) {
        this.toastText = text;
        setTimeout(() => { if (this.toastText === text) this.toastText = null; }, 3500);
    },

    // Bevestigen in een eigen venster in plaats van de grijze browserdialoog.
    ask(text, confirmLabel = 'Ja') {
        return new Promise((resolve) => { this.question = { text, confirmLabel, resolve }; });
    },
    answer(yes) {
        this.question?.resolve(yes);
        this.question = null;
    },
});

// Gegevens van een scherm: eerst wat er deze sessie al was (meteen zichtbaar), daarna vers van de server.
// Ook na een herstart of na een serverpagina (dan laadt de app opnieuw) staat het laatst geziene er meteen,
// daarna komt de verse versie. Per gebruiker, en weg bij uitloggen: een volgende gebruiker op deze
// telefoon ziet niets van de vorige.
const CACHE_PREFIX = 'renovion-os-cache:';
const memory = {};

const cache = {
    // Uit de opslag, niet uit de store: het vooraf laden begint al terwijl de store nog opstart.
    key: (name) => {
        let id = 'onbekend';
        try {
            id = JSON.parse(storage.get(PROFILE_KEY) ?? 'null')?.user?.id ?? id;
        } catch { /* kapot profiel: dan zonder id */ }
        return `${CACHE_PREFIX}${id}:${name}`;
    },
    read(name) {
        if (!(name in memory)) {
            const json = storage.get(cache.key(name));
            try {
                memory[name] = json ? { data: JSON.parse(json), json, at: Number(storage.get(`${cache.key(name)}:at`)) || null } : null;
            } catch {
                memory[name] = null;
            }
        }
        return memory[name];
    },
    write(name, data, json) {
        memory[name] = { data, json, at: Date.now() };
        storage.set(cache.key(name), json);
        cache.touch(name);
    },
    // Gelijk gebleven, maar wel net opgehaald: alleen de tijd bijwerken.
    touch(name) {
        if (memory[name]) memory[name].at = Date.now();
        storage.set(`${cache.key(name)}:at`, String(Date.now()));
    },
    clear() {
        Object.keys(memory).forEach((name) => delete memory[name]);
        try {
            Object.keys(localStorage).filter((key) => key.startsWith(CACHE_PREFIX)).forEach((key) => localStorage.removeItem(key));
        } catch { /* geen opslag */ }
    },
};

// Eén verzoek per scherm tegelijk, en kort na een verse lading niet nog een keer: het vooraf laden bij
// het opstarten en het openen van een scherm halen zo niet dubbel op.
const FRESH_MS = 20_000;
const inflight = {};
const fetchedAt = {};

function fetchScreen(name, path, { force = false } = {}) {
    if (inflight[name]) return inflight[name];
    if (!force && Date.now() - (fetchedAt[name] ?? 0) < FRESH_MS) return Promise.resolve(cache.read(name));
    inflight[name] = api(path)
        .then((fresh) => {
            const json = JSON.stringify(fresh);
            if (json !== cache.read(name)?.json) cache.write(name, fresh, json);
            else cache.touch(name);
            fetchedAt[name] = Date.now();
            return cache.read(name);
        })
        .finally(() => delete inflight[name]);
    return inflight[name];
}

// Bij het opstarten alles alvast ophalen, in de volgorde van de onderbalk en daarna Taken, zodat elk
// scherm al vers is als je erheen gaat.
async function preload(crm) {
    const screens = [['vandaag', 'vandaag'], crm ? ['inbox', 'inbox'] : null, ['projecten', 'projecten'], ['planning', 'planning'], ['taken', 'taken']].filter(Boolean);
    for (const [name, path] of screens) {
        try {
            await fetchScreen(name, path);
        } catch { /* offline: het scherm probeert het zelf weer bij openen */ }
    }
}

function screen(name, path) {
    return {
        cacheName: name,
        path,
        data: null,
        shownJson: null,
        failed: false,
        async init() {
            this.present(cache.read(this.cacheName));
            const first = this.data === null;
            await this.load();
            if (first && this.data) this.$nextTick(() => animateIn(this.$el));
        },
        // Ander filter of andere week: eerst wat er van die weergave bewaard is, dan vers.
        show(cacheName, path) {
            this.cacheName = cacheName;
            this.path = path;
            this.data = null;
            this.shownJson = null;
            this.present(cache.read(cacheName));
            return this.load();
        },
        // Alleen bijwerken als het echt anders is dan wat er nu staat, anders blijft alles rustig staan.
        present(entry) {
            if (!entry) return;
            Alpine.store('os').shownAt = entry.at;
            if (entry.json === this.shownJson) return;
            this.data = entry.data;
            this.shownJson = entry.json;
        },
        async load({ force = false } = {}) {
            const name = this.cacheName;
            this.failed = false;
            try {
                const entry = await fetchScreen(name, this.path, { force });
                if (name === this.cacheName) this.present(entry);
            } catch (error) {
                if (error.status !== 401 && name === this.cacheName) this.failed = this.data === null;
            }
        },
    };
}

// Blokken komen één voor één binnen, net als de pagina-opbouw op de server (alleen wat in beeld is).
function animateIn(root) {
    [...root.querySelectorAll('[data-enter]')]
        .filter((element) => element.getBoundingClientRect().top < innerHeight)
        .slice(0, 28)
        .forEach((element, index) => {
            element.style.setProperty('--enter-index', index);
            element.classList.add('enter-block');
            element.addEventListener('animationend', () => element.classList.remove('enter-block'), { once: true });
        });
}

Alpine.data('login', () => ({
    email: '',
    password: '',
    busy: false,
    error: null,
    async submit() {
        this.busy = true;
        this.error = null;
        try {
            const result = await api('inloggen', { method: 'POST', body: { email: this.email, password: this.password, device: 'Renovion OS-app' } });
            const { token, ...profile } = result;
            Alpine.store('os').remember(token, profile);
            this.password = '';
        } catch (error) {
            this.error = error.data?.errors?.email?.[0] ?? error.data?.message ?? 'Geen verbinding met de server.';
        }
        this.busy = false;
    },
}));

// Taak afronden vanaf Vandaag, Taken of het projectdossier
const finishing = {
    async finish(task) {
        task.done = true;
        try {
            await api(`taken/${task.id}/status`, { method: 'PATCH', body: { status: 'afgerond' } });
            await this.load({ force: true });
            fetchedAt.taken = fetchedAt.vandaag = 0; // de andere lijsten bij openen opnieuw
        } catch {
            task.done = false;
            Alpine.store('os').toast('Afronden lukte niet. Probeer het opnieuw.');
        }
    },
};

Alpine.data('today', () => ({ ...screen('vandaag', 'vandaag'), ...finishing }));

Alpine.data('inbox', () => ({
    ...screen('inbox', 'inbox'),
    filter: 'alles',
    get urgent() {
        return (this.data?.items ?? []).filter((item) => item.severity === 'rood').length;
    },
    shows(item) {
        return this.filter === 'alles' || (this.filter === 'rood') === (item.severity === 'rood');
    },
}));

Alpine.data('planning', () => ({
    ...screen('planning', 'planning'),
    dayIndex: null,
    detail: null,
    form: null,
    saving: false,
    formError: null,
    get day() {
        const days = this.data?.days ?? [];
        if (this.dayIndex !== null) return days[this.dayIndex];
        return days.find((day) => day.today) ?? days[0];
    },
    get dayNumber() {
        return (this.data?.days ?? []).indexOf(this.day);
    },
    // Uitvoerder: vanaf vandaag (deze week) of de hele week (andere weken)
    get agendaDays() {
        const days = this.data?.days ?? [];
        return this.data?.current ? days.filter((day) => !day.past) : days;
    },
    entriesFor(userId, date) {
        return (this.data?.entries ?? []).filter((entry) => entry.user_id === userId && entry.day === date);
    },
    goWeek(week) {
        this.dayIndex = null;
        return week ? this.show(`planning:${week}`, `planning?week=${week}`) : this.show('planning', 'planning');
    },
    plan(userId = '', date = null) {
        this.formError = null;
        this.form = { user_id: userId, date: date ?? this.day?.date, type: 'project', project_id: '', title: '', start_time: '', end_time: '' };
    },
    async save() {
        this.saving = true;
        this.formError = null;
        try {
            await api('planning', { method: 'POST', body: Object.fromEntries(Object.entries(this.form).filter(([, value]) => value !== '' && value !== null)) });
            this.form = null;
            await this.load({ force: true });
            Alpine.store('os').toast('Ingepland.');
        } catch (error) {
            this.formError = Object.values(error.data?.errors ?? {})[0]?.[0] ?? 'Opslaan lukte niet. Controleer de verbinding.';
        }
        this.saving = false;
    },
    async remove(entry) {
        if (!(await Alpine.store('os').ask('Deze planningsregel verwijderen?', 'Verwijderen'))) return;
        try {
            await api(`planning/${entry.id}`, { method: 'DELETE' });
            this.detail = null;
            await this.load({ force: true });
        } catch {
            Alpine.store('os').toast('Verwijderen lukte niet. Probeer het opnieuw.');
        }
    },
}));

Alpine.data('projects', () => ({
    ...screen('projecten', 'projecten'),
    status: null,
    filter(value) {
        this.status = value;
        return this.show(value ? `projecten:${value}` : 'projecten', value ? `projecten?status=${value}` : 'projecten');
    },
}));

Alpine.data('project', (id) => ({ ...screen(`project:${id}`, `projecten/${id}`), ...finishing }));

// Foto's kleiner maken vóór het versturen: een telefoonfoto van 4 MB wordt zo'n 400 kB, en dat scheelt
// veel op de bouwplaats met slecht bereik. Lukt het niet, dan gaat het origineel.
const PHOTO_MAX = 2000;
async function shrinkPhoto(file) {
    try {
        const bitmap = await createImageBitmap(file);
        const scale = Math.min(1, PHOTO_MAX / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));
        return blob && blob.size < file.size ? new File([blob], `${file.name.replace(/\.\w+$/, '')}.jpg`, { type: 'image/jpeg' }) : file;
    } catch {
        return file;
    }
}

// Werkpakket (mockup "Elektra begane grond"): afvinken, foto-bewijs en afronden. Elke actie krijgt het
// hele werkpakket terug. Een foto die door slecht bereik niet aankomt, blijft staan met "Opnieuw" en gaat
// vanzelf als de verbinding terug is (zolang de app open blijft).
Alpine.data('workPackage', (id) => {
    const base = screen(`werkpakket:${id}`, `werkpakketten/${id}`);

    return {
        ...base,
        busy: null,
        uploads: [],
        viewer: null,
        caption: '',

        init() {
            this.$watch('$store.os.offline', (offline) => !offline && this.retryUploads());
            return base.init.call(this);
        },
        destroy() {
            this.uploads.forEach((upload) => URL.revokeObjectURL(upload.preview));
        },

        photoUrl: (photo) => `${API}${photo.url}`,
        get doneCount() {
            return (this.data?.items ?? []).filter((item) => item.done).length;
        },
        get canComplete() {
            return Boolean(this.data?.checklist_complete && this.data?.evidence_complete);
        },
        needsPhotos: (item) => item.requires_photos > 0 && item.photos < item.requires_photos,
        uploadsFor(itemId) {
            return this.uploads.filter((upload) => upload.itemId === itemId);
        },

        // Nieuwe gegevens van de server: tonen, bewaren, en dossier, Projecten en Vandaag bij openen opnieuw.
        apply(fresh) {
            cache.write(this.cacheName, fresh, JSON.stringify(fresh));
            this.present(cache.read(this.cacheName));
            fetchedAt[`project:${fresh.project.id}`] = fetchedAt.vandaag = fetchedAt.projecten = 0;
        },
        fail(error, fallback) {
            Alpine.store('os').toast(error.data?.message ?? fallback);
        },

        // Snel achter elkaar afvinken mag: elk item wacht alleen op zichzelf. Liepen er meer tegelijk, dan kan
        // een antwoord achterlopen, dus dan aan het eind één keer vers ophalen.
        ticking: [],
        overlapped: false,
        async toggle(item) {
            if (this.ticking.includes(item.id) || this.data.done) return;
            if (!item.done && this.needsPhotos(item)) {
                Alpine.store('os').toast(`Maak eerst ${format.tally(item.requires_photos - item.photos, 'bewijsfoto', "bewijsfoto's")} bij dit punt.`);
                return;
            }
            if (this.ticking.length) this.overlapped = true;
            this.ticking.push(item.id);
            item.done = !item.done; // meteen zichtbaar, de server bevestigt
            let fresh = null;
            try {
                fresh = await api(`werkpakketten/${id}/items/${item.id}`, { method: 'PATCH' });
            } catch (error) {
                item.done = !item.done;
                this.fail(error, 'Afvinken lukte niet. Controleer de verbinding.');
            }
            this.ticking = this.ticking.filter((other) => other !== item.id);
            if (this.ticking.length) return;
            if (this.overlapped) {
                this.overlapped = false;
                await this.load({ force: true });
                if (this.data) fetchedAt[`project:${this.data.project.id}`] = fetchedAt.vandaag = fetchedAt.projecten = 0;
            } else if (fresh) {
                this.apply(fresh);
            }
        },

        async addPhotos(event, itemId = null) {
            const files = [...event.target.files];
            event.target.value = '';
            if (!files.length) return;
            const upload = {
                key: `${Date.now()}-${Math.random()}`,
                itemId,
                caption: itemId ? null : this.caption.trim() || null,
                preview: URL.createObjectURL(files[0]),
                count: files.length,
                files: [],
                state: 'busy',
            };
            if (!itemId) this.caption = '';
            this.uploads.push(upload);
            upload.files = await Promise.all(files.map(shrinkPhoto));
            await this.send(upload);
        },
        async send(upload) {
            upload.state = 'busy';
            const body = new FormData();
            upload.files.forEach((file) => body.append('photos[]', file));
            if (upload.itemId) body.append('checklist_item_id', upload.itemId);
            if (upload.caption) body.append('caption', upload.caption);
            try {
                this.apply(await api(`werkpakketten/${id}/fotos`, { method: 'POST', body }));
                this.drop(upload);
            } catch (error) {
                if (error instanceof ApiError) {
                    // De server weigert (te groot, geen afbeelding): opnieuw proberen heeft geen zin.
                    this.drop(upload);
                    this.fail(error, 'Uploaden lukte niet.');
                } else {
                    upload.state = 'failed';
                }
            }
        },
        drop(upload) {
            this.uploads = this.uploads.filter((other) => other.key !== upload.key);
            URL.revokeObjectURL(upload.preview);
        },
        retryUploads() {
            this.uploads.filter((upload) => upload.state === 'failed').forEach((upload) => this.send(upload));
        },

        async removePhoto(photo) {
            if (!(await Alpine.store('os').ask('Deze foto verwijderen?', 'Verwijderen'))) return;
            this.viewer = null;
            try {
                this.apply(await api(`fotos/${photo.id}`, { method: 'DELETE' }));
            } catch (error) {
                this.fail(error, 'Verwijderen lukte niet. Probeer het opnieuw.');
            }
        },

        async complete() {
            if (!this.canComplete) {
                Alpine.store('os').toast(this.data.checklist_complete
                    ? 'Het verplichte foto-bewijs is nog niet compleet.'
                    : `Rond eerst de checklist af (${this.doneCount}/${this.data.items.length}).`);
                return;
            }
            this.busy = 'complete';
            try {
                this.apply(await api(`werkpakketten/${id}/afronden`, { method: 'POST' }));
                Alpine.store('os').toast('Werkpakket afgerond.');
            } catch (error) {
                this.fail(error, 'Afronden lukte niet. Controleer de verbinding.');
            }
            this.busy = null;
        },
        async reopen() {
            if (!(await Alpine.store('os').ask('Dit werkpakket weer openzetten?', 'Heropenen'))) return;
            this.busy = 'complete';
            try {
                this.apply(await api(`werkpakketten/${id}/heropenen`, { method: 'POST' }));
            } catch (error) {
                this.fail(error, 'Heropenen lukte niet. Controleer de verbinding.');
            }
            this.busy = null;
        },
    };
});

Alpine.data('tasks', () => ({
    ...screen('taken', 'taken'),
    ...finishing,
    status: null,
    form: null,
    saving: false,
    formError: null,
    filter(value) {
        this.status = value;
        return this.show(value ? `taken:${value}` : 'taken', value ? `taken?status=${value}` : 'taken');
    },
    async setStatus(task, status) {
        try {
            await api(`taken/${task.id}/status`, { method: 'PATCH', body: { status } });
            await this.load({ force: true });
            fetchedAt.vandaag = 0;
        } catch {
            Alpine.store('os').toast('Bijwerken lukte niet. Probeer het opnieuw.');
        }
    },
    add() {
        this.formError = null;
        this.form = { title: '', deadline: new Date().toISOString().slice(0, 10), priority: 'normaal', owner_id: '', project_id: '' };
    },
    async save() {
        this.saving = true;
        this.formError = null;
        try {
            await api('taken', { method: 'POST', body: Object.fromEntries(Object.entries(this.form).filter(([, value]) => value !== '' && value !== null)) });
            this.form = null;
            await this.load({ force: true });
            fetchedAt.vandaag = 0;
            Alpine.store('os').toast('Taak aangemaakt.');
        } catch (error) {
            this.formError = Object.values(error.data?.errors ?? {})[0]?.[0] ?? 'Opslaan lukte niet. Controleer de verbinding.';
        }
        this.saving = false;
    },
}));

// Nova: dezelfde stappen als op de website (voorstel, jij bevestigt), via de app-API.
Alpine.data('nova', () => ({
    busy: false,
    input: '',
    viaVoice: false,
    listening: false,
    recognition: null,
    messages: [],
    get speechSupported() {
        return 'webkitSpeechRecognition' in window || 'SpeechRecognition' in window;
    },
    init() {
        this.$watch('$store.os.nova', (open) => {
            if (!open) return;
            if (this.messages.length === 0) {
                this.messages.push({ role: 'nova', text: 'Hoi! Wat kan ik voor je klaarzetten? Bijvoorbeeld: "Plan morgen om 10:00 een terugbelafspraak met familie Jansen".' });
            }
            this.$nextTick(() => this.$refs.novaInput?.focus());
        });
    },
    toggleMic() {
        if (this.listening) {
            this.recognition?.stop();
            return;
        }
        const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        this.recognition = new Recognition();
        this.recognition.lang = 'nl-NL';
        this.recognition.interimResults = true;
        this.recognition.onresult = (event) => {
            this.input = Array.from(event.results).map((result) => result[0].transcript).join('');
            if (event.results[event.results.length - 1].isFinal) {
                this.viaVoice = true;
                this.send();
            }
        };
        this.recognition.onend = () => { this.listening = false; };
        this.recognition.onerror = () => { this.listening = false; };
        this.listening = true;
        this.recognition.start();
    },
    scrollDown() {
        this.$nextTick(() => { const panel = this.$refs.novaMessages; if (panel) panel.scrollTop = panel.scrollHeight; });
    },
    async send() {
        const text = this.input.trim();
        if (!text || this.busy) return;
        const viaVoice = this.viaVoice;
        this.input = '';
        this.viaVoice = false;
        this.messages.push({ role: 'user', text });
        this.busy = true;
        this.scrollDown();
        try {
            const data = await api('nova', { method: 'POST', body: { message: text } });
            this.messages.push(data.type === 'proposal'
                ? { role: 'nova', text: 'Dit zet ik voor je klaar. Akkoord?', proposal: { action: data.action, preview: data.preview, viaVoice, state: 'open' } }
                : { role: 'nova', text: data.text });
        } catch (error) {
            this.messages.push({ role: 'nova', text: error.data?.text ?? error.data?.message ?? 'Nova is nu niet bereikbaar. Probeer het zo nog eens.' });
        }
        this.busy = false;
        this.scrollDown();
    },
    async confirm(message) {
        if (message.proposal.state !== 'open') return;
        message.proposal.state = 'busy';
        try {
            const data = await api('nova/uitvoeren', { method: 'POST', body: { action: message.proposal.action, via_voice: message.proposal.viaVoice } });
            message.proposal.state = 'done';
            this.messages.push({ role: 'nova', text: data.message, path: data.url ? new URL(data.url, API).pathname : null });
            Object.keys(fetchedAt).forEach((name) => { fetchedAt[name] = 0; }); // lijsten bij openen opnieuw
        } catch (error) {
            message.proposal.state = 'open';
            this.messages.push({ role: 'nova', text: error.data?.message ?? 'Uitvoeren lukte niet. Probeer het opnieuw.' });
        }
        this.scrollDown();
    },
    cancel(message) {
        if (message.proposal.state !== 'open') return;
        message.proposal.state = 'cancelled';
        this.messages.push({ role: 'nova', text: 'Oké, niet uitgevoerd. Zeg het maar als ik iets anders kan klaarzetten.' });
        this.scrollDown();
    },
}));

// <span x-icon="'sun'" class="h-5 w-5"></span>: dezelfde iconen als <x-icon> op de server.
Alpine.directive('icon', (el, { expression }, { evaluateLater, effect }) => {
    const name = evaluateLater(expression);
    el.classList.add('os-icon');
    effect(() => name((value) => { el.innerHTML = iconSvg(value); }));
});

window.Alpine = Alpine;
Alpine.plugin(swipeDismiss);
Alpine.start();
