// Gedeeld door de formulierschermen (screens/*formulier.js): laden van keuzelijsten en huidige waarden
// (api/app/formulieren/*, Api\App\FormController), fouten per veld uit de 422 van Laravel, opslaan via
// dezelfde controllers als de website en daarna naar het record ({ id, path } uit het antwoord).
// Staat in een submap, zodat main.js het niet als scherm laadt (import.meta.glob('./screens/*.js')).

// Query van het scherm (#/nieuweaanvraag?aanvraag=5): $store.os.query, of anders uit het adres zelf.
export function queryOf(Alpine) {
    const query = Alpine.store('os').query;
    if (query && typeof query === 'object') return query;
    return Object.fromEntries(new URLSearchParams(location.hash.split('?')[1] ?? ''));
}

// '?aanvraag=5&klant=3' uit de bekende sleutels van de query, leeg als er niets is.
export function querySuffix(query, keys) {
    const params = new URLSearchParams();
    keys.forEach((key) => { if (/^\d+$/.test(String(query[key] ?? ''))) params.set(key, query[key]); });
    const text = params.toString();
    return text ? `?${text}` : '';
}

// Lege velden niet meesturen bij aanmaken (zoals de website: een leeg veld = niet ingevuld).
export const filled = (body) => Object.fromEntries(Object.entries(body).filter(([, value]) => value !== '' && value !== null && value !== undefined));

// Inspreken in een tekstveld (Web Speech API; native.js levert dezelfde API op de native spraakplugin).
// dictate(() => tekst, (tekst) => ...): plakt wat je zegt achter wat er al stond.
export function dictation() {
    return {
        listening: false,
        recognition: null,
        speechSupported: 'webkitSpeechRecognition' in window || 'SpeechRecognition' in window,
        dictate(read, write) {
            if (!this.speechSupported) return;
            if (this.listening) {
                this.recognition?.stop();
                return;
            }
            const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            const start = (read() ?? '').trim();
            this.recognition = new Recognition();
            this.recognition.lang = 'nl-NL';
            this.recognition.interimResults = true;
            this.recognition.onresult = (event) => {
                const spoken = Array.from(event.results).map((result) => result[0].transcript).join('');
                write(start ? `${start} ${spoken}` : spoken);
                this.dirty = true;
            };
            this.recognition.onend = () => { this.listening = false; };
            this.recognition.onerror = () => { this.listening = false; };
            this.listening = true;
            this.recognition.start();
        },
        destroy() {
            this.recognition?.abort?.();
        },
    };
}

/**
 * name: cachenaam (bewaarde keuzelijsten), path: api/app-pad, fill(data): het formuliermodel uit de gegevens.
 * Het model wordt alleen opnieuw gevuld zolang je nog niets hebt veranderd (dirty).
 */
export function formScreen({ Alpine, fetchScreen, fetchedAt, cache, errorText, animateIn, api }, { name, path, fill }) {
    return {
        cacheName: name,
        data: null,
        shownJson: null,
        form: null,
        failed: false,
        unavailable: null, // tekst als het formulier niet mag of niet kan (403, of 422 zoals "alleen concepten")
        errors: {},
        formError: null,
        saving: false,
        dirty: false,
        async init() {
            this.present(cache.read(name));
            const first = this.data === null;
            await this.load();
            if (first && this.data) this.$nextTick(() => animateIn(this.$el));
        },
        present(entry) {
            if (!entry || entry.json === this.shownJson) return;
            this.data = entry.data;
            this.shownJson = entry.json;
            if (!this.dirty) this.form = fill(entry.data);
        },
        async load({ force = false } = {}) {
            this.failed = false;
            try {
                this.present(await fetchScreen(name, path, { force }));
                this.unavailable = null;
            } catch (error) {
                if (error.status === 403) this.unavailable = 'Je rol heeft geen toegang tot dit formulier.';
                else if (error.status === 404) this.unavailable = 'Dit bestaat niet meer.';
                else if (error.status === 422) this.unavailable = error.data?.message ?? 'Dit formulier kan nu niet.';
                else if (error.status !== 401) this.failed = this.data === null;
            }
        },
        // Eerste foutmelding van een veld (ook 'lines.0.description').
        err(field) {
            return this.errors[field]?.[0] ?? null;
        },
        // Typen in een veld: het formulier is gewijzigd en de fout van dat veld gaat weg.
        touch(event) {
            this.dirty = true;
            const field = event?.target?.name;
            if (field && this.errors[field]) {
                const { [field]: _removed, ...rest } = this.errors;
                this.errors = rest;
            }
        },
        // Versturen; bij succes de bewaarde schermen die het tonen verversen en naar het record.
        async submit(apiPath, body, { method = 'POST', success = 'Opgeslagen.', refresh = [], go = true } = {}) {
            if (this.saving) return null;
            this.saving = true;
            this.errors = {};
            this.formError = null;
            try {
                const result = await api(apiPath, { method, body });
                [name, ...refresh].forEach((screen) => { fetchedAt[screen] = 0; });
                this.dirty = false;
                Alpine.store('os').toast(success);
                if (go && result?.path) Alpine.store('os').go(result.path);
                return result;
            } catch (error) {
                if (error.status === 422 && error.data?.errors) {
                    this.errors = error.data.errors;
                    this.formError = 'Niet alles is goed ingevuld. Kijk bij de rode meldingen.';
                    this.$nextTick(() => [...this.$el.querySelectorAll('[data-error]')]
                        .find((element) => element.offsetParent !== null)
                        ?.scrollIntoView({ block: 'center', behavior: 'smooth' }));
                } else {
                    this.formError = errorText(error, 'Opslaan lukte niet. Controleer de verbinding.');
                }
                return null;
            } finally {
                this.saving = false;
            }
        },
        // Annuleren: terug naar waar je vandaan kwam, of anders naar het overzicht van het formulier.
        cancel() {
            if (history.length > 1) history.back();
            else if (this.data?.cancel) Alpine.store('os').go(this.data.cancel);
        },
    };
}
