import Alpine from 'alpinejs';
import './native.js';
import './keyboard.js';
import './motion.js';
import swipeDismiss from './swipe-dismiss.js';
import meetingRecorder from './meeting-recorder.js';

window.Alpine = Alpine;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

async function sendJson(method, url, data) {
    const response = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(data),
    });

    if (!response.ok) {
        const body = await response.json().catch(() => ({}));
        throw Object.assign(new Error(body.message || `Verzoek mislukt (${response.status})`), { body });
    }

    return response.json();
}

// Dubbel opslaan voorkomen (trage verbinding op de bouw): na de eerste verzending wordt een tweede
// genegeerd en de knop grijs. Pas na het versturen uitschakelen, anders valt de waarde van de
// ingedrukte knop (name/value) weg. Formulieren met een bevestiging of Alpine (@submit.prevent)
// zetten defaultPrevented en vallen hier buiten; data-allow-resubmit zet het uit.
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (event.defaultPrevented || 'allowResubmit' in form.dataset) return;
    if (form.dataset.submitting) {
        event.preventDefault();
        return;
    }
    form.dataset.submitting = '1';
    setTimeout(() => form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => {
        button.disabled = true;
        button.classList.add('opacity-60');
    }), 0);
});
// Melding onderaan in beeld in plaats van bovenaan de pagina (opslaan zonder naar boven te springen).
const FLOATING_FLASH = ['fixed', 'inset-x-4', 'bottom-[calc(6rem_+_env(safe-area-inset-bottom))]', 'app:bottom-[calc(9.5rem_+_env(safe-area-inset-bottom))]', 'z-50', 'shadow-lg', 'lg:inset-x-auto', 'lg:right-8', 'lg:bottom-8', 'lg:w-96'];

// Opslaan zonder herladen: <form data-live> gaat op de achtergrond, en de blokken met data-live-region (met
// een id) krijgen de nieuwe versie uit het antwoord. Wat open of ingeklapt was (Alpine "open") blijft zo.
// Komt het antwoord op een andere pagina uit, of gaat er iets mis, dan alsnog gewoon versturen.
function showFlash(flash) {
    document.querySelectorAll('[data-flash]').forEach((old) => old.remove());
    if (!flash) return;
    flash.classList.add(...FLOATING_FLASH);
    document.querySelector('main')?.prepend(flash);
}
// Wat er open stond (uitgeklapte fasen, "Alle fasen bekijken", een <details>-venster) blijft open na het
// vervangen. Herkend aan het id, anders aan de plek in het blok (de server levert dezelfde opbouw). Het
// venster waaruit je net verstuurde ("Vrijgeven") gaat wel dicht.
const placeOf = (el, root) => {
    if (el.id) return `#${el.id}`;
    const path = [];
    for (let node = el; node !== root; node = node.parentElement) path.unshift([...node.parentElement.children].indexOf(node));
    return path.join('.');
};
const findPlace = (root, place) => (place.startsWith('#')
    ? root.querySelector(place.replace(/^#(.*)$/, (_, id) => `#${CSS.escape(id)}`))
    : place.split('.').reduce((node, index) => node?.children[index], root));
function rememberOpen(region, form) {
    const alpine = [...region.querySelectorAll('[x-data]')]
        .filter((el) => typeof el._x_dataStack?.[0]?.open === 'boolean')
        .map((el) => [placeOf(el, region), el._x_dataStack[0].open]);
    const details = [...region.querySelectorAll('details[open]')].filter((el) => !el.contains(form)).map((el) => placeOf(el, region));
    return { alpine, details };
}
function restoreOpen(region, { alpine, details }) {
    alpine.forEach(([place, open]) => {
        const data = findPlace(region, place)?._x_dataStack?.[0];
        if (data && typeof data.open === 'boolean') data.open = open;
    });
    details.forEach((place) => findPlace(region, place)?.setAttribute('open', ''));
}

async function liveSubmit(form) {
    const regions = [...document.querySelectorAll('[data-live-region][id]')];
    let response;
    try {
        response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'text/html' }, credentials: 'same-origin' });
    } catch {
        form.submit();
        return;
    }
    const target = new URL(response.url);
    if (!response.ok || target.pathname !== location.pathname) {
        location.href = response.ok ? response.url : location.href;
        return;
    }

    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
    regions.forEach((region) => {
        const fresh = page.getElementById(region.id);
        if (!fresh) return;
        const opened = rememberOpen(region, form);
        Alpine.mutateDom(() => region.replaceWith(fresh));
        Alpine.initTree(fresh);
        restoreOpen(fresh, opened);
    });
    showFlash(page.querySelector('[data-flash]'));

    // Stond het formulier zelf niet in een vervangen blok, dan mag het weer.
    if (form.isConnected) {
        delete form.dataset.submitting;
        form.querySelectorAll('button[disabled].opacity-60').forEach((button) => {
            button.disabled = false;
            button.classList.remove('opacity-60');
        });
    }
}
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!form.matches('form[data-live]') || event.defaultPrevented) return;
    event.preventDefault();
    liveSubmit(form);
});

// Op je plek blijven: komt een formulier (opslaan, status wijzigen, afvinken) terug op dezelfde pagina,
// dan staat die weer waar je was in plaats van bovenaan. Via pagehide, want this.form.submit() (keuzelijsten)
// geeft geen submit-event. De melding staat dan onderaan in beeld in plaats van bovenaan de pagina.
const KEEP_PLACE = 'renovion-place';
window.addEventListener('pagehide', () => {
    try {
        sessionStorage.setItem(KEEP_PLACE, JSON.stringify({ url: location.pathname + location.search, y: scrollY, at: Date.now() }));
    } catch { /* geen opslag */ }
});
function keepPlace() {
    let place = null;
    try {
        place = JSON.parse(sessionStorage.getItem(KEEP_PLACE) ?? 'null');
        sessionStorage.removeItem(KEEP_PLACE);
    } catch { /* geen opslag */ }
    const navigation = performance.getEntriesByType('navigation')[0];
    if (!place || place.y < 80 || navigation?.type !== 'navigate' || Date.now() - place.at > 60_000) return;
    if (place.url !== location.pathname + location.search) return;

    scrollTo({ top: place.y, behavior: 'instant' });
    document.querySelector('[data-flash]')?.classList.add(...FLOATING_FLASH);
}
// Na Alpine: tabs en ingeklapte blokken staan dan goed, anders klopt de hoogte van de pagina nog niet.
document.addEventListener('alpine:initialized', () => requestAnimationFrame(keepPlace));

window.addEventListener('pageshow', () => document.querySelectorAll('form[data-submitting]').forEach((form) => {
    delete form.dataset.submitting;
    form.querySelectorAll('button[disabled].opacity-60').forEach((button) => {
        button.disabled = false;
        button.classList.remove('opacity-60');
    });
}));


// Foto schermvullend bekijken (components/photo-viewer): elke [data-photo] op de pagina opent hem.
document.addEventListener('click', (event) => {
    const trigger = event.target.closest?.('[data-photo]');
    if (!trigger) return;
    event.preventDefault();
    window.dispatchEvent(new CustomEvent('photo-open', { detail: trigger }));
});

Alpine.data('photoViewer', () => {
    const SWIPE = 60;
    const MAX_ZOOM = 5;
    const pointers = new Map();
    let pinch = null;
    let drag = null;
    let lastTap = 0;

    const distance = () => {
        const [a, b] = [...pointers.values()];
        return Math.hypot(a.x - b.x, a.y - b.y);
    };

    return {
        isOpen: false,
        items: [],
        index: 0,
        scale: 1,
        x: 0,
        y: 0,

        get item() {
            return this.items[this.index] ?? {};
        },

        get transform() {
            return `translate(${this.x}px, ${this.y}px) scale(${this.scale})`;
        },

        // Alleen wat nu zichtbaar is, zodat het fase-filter en verborgen tabs meetellen.
        show(trigger) {
            const seen = new Set();
            this.items = [...document.querySelectorAll('[data-photo]')]
                .filter((el) => el === trigger || el.offsetParent !== null)
                .filter((el) => !seen.has(el.dataset.photo) && seen.add(el.dataset.photo))
                .map((el) => ({ src: el.dataset.photo, caption: el.dataset.caption, meta: el.dataset.meta }));
            this.index = Math.max(0, this.items.findIndex((item) => item.src === trigger.dataset.photo));
            this.reset();
            this.isOpen = true;
            document.documentElement.classList.add('overflow-hidden');
            this.$nextTick(() => window.dispatchEvent(new Event('renovion-top-changed')));
        },

        close() {
            this.isOpen = false;
            document.documentElement.classList.remove('overflow-hidden');
            setTimeout(() => window.dispatchEvent(new Event('renovion-top-changed')), 200); // na de fade-out
        },

        go(step) {
            const next = this.index + step;
            if (next < 0 || next >= this.items.length) return;
            this.index = next;
            this.reset();
        },

        reset() {
            this.scale = 1;
            this.x = 0;
            this.y = 0;
            this.cancel();
        },

        cancel() {
            pointers.clear();
            pinch = null;
            drag = null;
        },

        down(event) {
            event.currentTarget.setPointerCapture(event.pointerId);
            pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
            if (pointers.size === 2) {
                pinch = { distance: distance(), scale: this.scale };
                drag = null;
            } else if (pointers.size === 1) {
                drag = { startX: event.clientX, startY: event.clientY, x: this.x, y: this.y };
            }
        },

        move(event) {
            if (!pointers.has(event.pointerId)) return;
            pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
            if (pinch && pointers.size === 2) {
                this.scale = Math.min(MAX_ZOOM, Math.max(1, pinch.scale * (distance() / pinch.distance)));
            } else if (drag && this.scale > 1) {
                this.x = drag.x + event.clientX - drag.startX;
                this.y = drag.y + event.clientY - drag.startY;
            }
        },

        up(event) {
            pointers.delete(event.pointerId);
            if (pinch) {
                if (pointers.size === 0) pinch = null;
                if (this.scale === 1) { this.x = 0; this.y = 0; }
                return;
            }
            if (!drag) return;

            const moveX = event.clientX - drag.startX;
            const moveY = event.clientY - drag.startY;
            drag = null;

            if (Math.hypot(moveX, moveY) < 10) {
                const now = Date.now();
                if (now - lastTap < 300) {
                    this.scale = this.scale > 1 ? 1 : 2.5;
                    this.x = 0;
                    this.y = 0;
                }
                lastTap = now;
            } else if (this.scale === 1) {
                if (Math.abs(moveX) > SWIPE && Math.abs(moveX) > Math.abs(moveY)) this.go(moveX < 0 ? 1 : -1);
                else if (moveY > SWIPE * 2) this.close();
            }
        },
    };
});

// PATCH-helper voor statuswijzigingen (Kanban, taken) met CSRF-token.
window.patchJson = (url, data) => sendJson('PATCH', url, data);
window.postJson = (url, data) => sendJson('POST', url, data);

// Teamchat (briefing §11): berichten versturen, pollen op nieuwe berichten,
// Nova-voorstellen bevestigen en taken maken vanuit een bericht.
Alpine.data('chat', (config) => ({
    messages: config.messages || [],
    lastId: 0,
    input: '',
    busy: false,
    listening: false,
    recognition: null,
    pollTimer: null,
    toast: null,
    actionsFor: null,

    init() {
        this.lastId = this.messages.length ? this.messages[this.messages.length - 1].id : 0;
        this.scrollDown();
        this.pollTimer = setInterval(() => this.poll(), 8000);
        this.fitToScreen();
        // Toetsenbord open of dicht: het gesprek past zich aan en het nieuwste bericht blijft in beeld.
        window.visualViewport?.addEventListener('resize', () => {
            this.fitToScreen();
            this.scrollDown();
        });
    },

    // Op de telefoon vult het gesprek het zichtbare deel van het scherm, ook naast het toetsenbord.
    fitToScreen() {
        const panel = this.$refs.panel;
        const height = window.visualViewport?.height;
        if (!panel || !height) return;
        panel.style.height = window.matchMedia('(min-width: 64rem)').matches ? '' : `${height}px`;
    },

    // Reeksen van dezelfde afzender horen bij elkaar: één naam, één avatar, één tijd.
    get thread() {
        const SAME_THREAD_SECONDS = 600;
        const sameGroup = (earlier, later) => earlier && later
            && earlier.author === later.author
            && earlier.day === later.day
            && later.at - earlier.at < SAME_THREAD_SECONDS;

        return this.messages.map((message, index) => {
            const previous = this.messages[index - 1];
            return {
                message,
                newDay: previous?.day !== message.day,
                startsGroup: !sameGroup(previous, message),
                endsGroup: !sameGroup(message, this.messages[index + 1]),
            };
        });
    },

    // Vaste kleur per persoon, alleen zichtbaar in de iOS/Android-app (native-app.css).
    nameColor(name) {
        const colors = ['#c2506d', '#3e6ba8', '#1e8a5a', '#7c5cd6', '#2b8c8c', '#d98a0b'];
        return colors[[...name].reduce((sum, letter) => sum + letter.charCodeAt(0), 0) % colors.length];
    },

    toggleActions(message) {
        this.actionsFor = this.actionsFor === message.id ? null : message.id;
    },

    destroy() {
        clearInterval(this.pollTimer);
    },

    get speechSupported() {
        return 'webkitSpeechRecognition' in window || 'SpeechRecognition' in window;
    },

    toggleMic() {
        if (!this.speechSupported) return;
        if (this.listening) { this.recognition?.stop(); return; }

        const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        const startingText = this.input ? this.input.trim() + ' ' : '';
        this.recognition = new Recognition();
        this.recognition.lang = 'nl-NL';
        this.recognition.interimResults = true;
        this.recognition.onresult = (event) => {
            this.input = startingText + Array.from(event.results).map((r) => r[0].transcript).join('');
        };
        this.recognition.onend = () => { this.listening = false; };
        this.recognition.onerror = () => { this.listening = false; };
        this.listening = true;
        this.recognition.start();
    },

    format(body) {
        if (!body) return '';
        return body
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
            .replace(/@([\wÀ-ÿ-]+)/g, '<span class="font-semibold text-brand-600">@$1</span>')
            .replace(/\n/g, '<br>');
    },

    append(list) {
        for (const incoming of list) {
            const index = this.messages.findIndex((m) => m.id === incoming.id);
            if (index >= 0) this.messages.splice(index, 1, incoming);
            else this.messages.push(incoming);
        }
        if (this.messages.length) this.lastId = Math.max(...this.messages.map((m) => m.id));
        this.scrollDown();
    },

    async poll() {
        try {
            const response = await fetch(config.messagesUrl + '?after=' + this.lastId, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const data = await response.json();
            if (data.messages.length) this.append(data.messages);
        } catch { /* volgende poll probeert opnieuw */ }
    },

    async send() {
        const text = this.input.trim();
        if (!text || this.busy) return;
        this.busy = true;
        this.input = '';
        try {
            const data = await postJson(config.messagesUrl, { body: text });
            this.append(data.messages);
        } catch (error) {
            this.input = text;
            this.flash(error.message);
        }
        this.busy = false;
    },

    async attach(event) {
        const file = event.target.files[0];
        if (!file || this.busy) return;
        this.busy = true;
        const form = new FormData();
        form.append('attachment', file);
        if (this.input.trim()) { form.append('body', this.input.trim()); this.input = ''; }
        try {
            const response = await fetch(config.messagesUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: form,
            });
            if (!response.ok) throw new Error('Upload mislukt.');
            this.append((await response.json()).messages);
        } catch (error) {
            this.flash(error.message);
        }
        event.target.value = '';
        this.busy = false;
    },

    async confirmNova(message) {
        if (message.nova?.executed) return;
        try {
            const data = await postJson(config.confirmUrl.replace('__ID__', message.id), {});
            this.append(data.messages);
        } catch (error) {
            this.flash(error.message);
        }
    },

    async toTask(message) {
        try {
            const data = await postJson(config.taskUrl.replace('__ID__', message.id), {});
            this.actionsFor = null;
            this.flash(data.message);
        } catch (error) {
            this.flash(error.message);
        }
    },

    flash(text) {
        this.toast = text;
        setTimeout(() => { this.toast = null; }, 3500);
    },

    scrollDown() {
        this.$nextTick(() => {
            const el = this.$refs.chatScroll;
            if (el) el.scrollTop = el.scrollHeight;
        });
    },
}));

// Calculatie-invoer (briefing §5): omschrijving (getypt of ingesproken) → AI-regelvoorstel
// dat de gebruiker eerst controleert en pas daarna opslaat.
Alpine.data('calcCreate', (proposeUrl, initialDescription, initialSources) => ({
    mode: 'handmatig',
    description: initialDescription || '',
    sources: initialSources || [],
    busy: false,
    listening: false,
    recognition: null,
    error: null,
    proposal: null,

    get speechSupported() {
        return 'webkitSpeechRecognition' in window || 'SpeechRecognition' in window;
    },

    get proposalTotal() {
        if (!this.proposal) return 0;
        return this.proposal.lines.reduce((sum, line) => sum + this.lineTotal(line), 0);
    },

    lineTotal(line) {
        return line.quantity * line.unit_price * (1 + (line.surcharge_pct || 0) / 100);
    },

    euro(value) {
        return new Intl.NumberFormat('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
    },

    toggleSource(source) {
        this.sources = this.sources.includes(source)
            ? this.sources.filter((item) => item !== source)
            : [...this.sources, source];
    },

    removeLine(index) {
        this.proposal.lines.splice(index, 1);
        if (this.proposal.lines.length === 0) this.proposal = null;
    },

    toggleMic() {
        if (!this.speechSupported) return;

        if (this.listening) {
            this.recognition?.stop();
            return;
        }

        const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        const startingText = this.description ? this.description.trim() + ' ' : '';
        this.recognition = new Recognition();
        this.recognition.lang = 'nl-NL';
        this.recognition.interimResults = true;

        this.recognition.onresult = (event) => {
            const transcript = Array.from(event.results).map((r) => r[0].transcript).join('');
            this.description = startingText + transcript;
        };
        this.recognition.onend = () => { this.listening = false; };
        this.recognition.onerror = () => { this.listening = false; };

        this.listening = true;
        this.recognition.start();
    },

    async propose() {
        if (!this.description.trim() || this.busy) return;

        this.busy = true;
        this.error = null;
        this.proposal = null;

        try {
            this.proposal = await postJson(proposeUrl, { description: this.description, sources: this.sources });
        } catch (error) {
            this.error = error.message;
        }

        this.busy = false;
    },
}));

// Nova-assistent: chat + voorstel/bevestiging + browser-spraakherkenning (nl-NL).
// Schil van de iOS/Android-app (partials/app): wat bovenop ligt en de teller die topbalk en menu delen.
// Dezelfde afspraak als elke sheet: de Android-terugknop sluit eerst wat open staat.
Alpine.store('shell', { drawer: false, help: false, unread: 0 });

window.addEventListener('renovion-back', (event) => {
    const shell = Alpine.store('shell');
    if (!shell.drawer && !shell.help) return;
    shell.drawer = shell.help = false;
    event.preventDefault();
});

Alpine.data('nova', (proposeUrl, executeUrl) => ({
    open: false,
    busy: false,
    input: '',
    viaVoice: false,
    listening: false,
    recognition: null,
    messages: [],

    get speechSupported() {
        return 'webkitSpeechRecognition' in window || 'SpeechRecognition' in window;
    },

    openPanel() {
        this.open = true;
        if (this.messages.length === 0) {
            this.messages.push({
                role: 'nova',
                text: 'Hoi! Wat kan ik voor je klaarzetten? Bijvoorbeeld: "Plan morgen om 10:00 een terugbelafspraak met familie Jansen" of "Zet voor vrijdag een taak om de aanbetaling van Bakker te controleren".',
            });
        }
        this.$nextTick(() => this.$refs.novaInput?.focus());
    },

    toggleMic() {
        if (!this.speechSupported) return;

        if (this.listening) {
            this.recognition?.stop();
            return;
        }

        const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        this.recognition = new Recognition();
        this.recognition.lang = 'nl-NL';
        this.recognition.interimResults = true;

        this.recognition.onresult = (event) => {
            const transcript = Array.from(event.results).map((r) => r[0].transcript).join('');
            this.input = transcript;
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
        this.$nextTick(() => {
            const panel = this.$refs.novaMessages;
            if (panel) panel.scrollTop = panel.scrollHeight;
        });
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
            const data = await postJson(proposeUrl, { message: text });
            if (data.type === 'proposal') {
                this.messages.push({
                    role: 'nova',
                    text: 'Dit zet ik voor je klaar — akkoord?',
                    proposal: { action: data.action, preview: data.preview, viaVoice, state: 'open' },
                });
            } else {
                this.messages.push({ role: 'nova', text: data.text });
            }
        } catch (error) {
            this.messages.push({ role: 'nova', text: 'Er ging iets mis: ' + error.message });
        }

        this.busy = false;
        this.scrollDown();
        this.$nextTick(() => this.$refs.novaInput?.focus());
    },

    async confirm(message) {
        if (message.proposal.state !== 'open') return;
        message.proposal.state = 'busy';

        try {
            const data = await postJson(executeUrl, {
                action: message.proposal.action,
                via_voice: message.proposal.viaVoice,
            });
            message.proposal.state = 'done';
            this.messages.push({ role: 'nova', text: data.message, url: data.url });
        } catch (error) {
            message.proposal.state = 'open';
            this.messages.push({ role: 'nova', text: error.message });
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

Alpine.plugin(swipeDismiss);
Alpine.plugin(meetingRecorder);
Alpine.start();

/*
 * Beweging (briefing hoofdstuk 6). De CSS doet de animaties; dit script
 * bepaalt alleen de volgorde van de blokken, de draaihoek van de waaier en
 * laat KPI-cijfers optellen. Alles gebeurt eenmalig bij het laden van een
 * pagina, nooit bij opslaan of filteren.
 */
const motionAllowed = () =>
    document.body.dataset.motion === 'on'
    && ! window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function staggerBlocks() {
    const main = document.querySelector('main');
    if (! main) return;

    // Maximaal 28 blokken in beeld; daarna zou het wachten gaan opvallen.
    Array.from(main.children).slice(0, 28).forEach((block, index) => {
        block.style.setProperty('--motion-i', index);
        block.classList.add('motion-block');
    });
}

function fanCards() {
    document.querySelectorAll('[data-motion-fan]').forEach((group) => {
        const cards = Array.from(group.children);
        const middle = (cards.length - 1) / 2;

        cards.forEach((card, index) => {
            card.style.setProperty('--motion-rotate', `${((index - middle) * 2.4).toFixed(2)}deg`);
            card.style.setProperty('--motion-i', index);
            card.classList.add('motion-fan');
        });
    });
}

/**
 * KPI-cijfers tellen op van 0 naar hun echte waarde, met behoud van de
 * Nederlandse notatie (punt als duizendtal, komma als decimaal) en van
 * alles wat er omheen staat, zoals een euroteken of een procentteken.
 */
function countUpNumbers() {
    document.querySelectorAll('[data-count]').forEach((element) => {
        const text = element.textContent.trim();
        const match = text.match(/-?\d[\d.]*(,\d+)?/);
        if (! match) return;

        const raw = match[0];
        const target = parseFloat(raw.replace(/\./g, '').replace(',', '.'));
        if (! isFinite(target) || target === 0) return;

        const decimals = raw.includes(',') ? raw.split(',')[1].length : 0;
        const prefix = text.slice(0, match.index);
        const suffix = text.slice(match.index + raw.length);
        const format = (value) => value.toLocaleString('nl-NL', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });

        const duration = 1000;
        const startAt = performance.now() + 250;
        element.textContent = `${prefix}${format(0)}${suffix}`;

        const tick = (now) => {
            const progress = Math.min(1, Math.max(0, (now - startAt) / duration));
            const eased = 1 - Math.pow(1 - progress, 3);
            element.textContent = `${prefix}${format(target * eased)}${suffix}`;

            if (progress < 1) requestAnimationFrame(tick);
        };

        requestAnimationFrame(tick);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (! motionAllowed()) return;

    staggerBlocks();
    fanCards();
    countUpNumbers();
});
