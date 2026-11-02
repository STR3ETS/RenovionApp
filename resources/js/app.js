import Alpine from 'alpinejs';
import './native.js';
import './motion.js';

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
window.addEventListener('pageshow', () => document.querySelectorAll('form[data-submitting]').forEach((form) => {
    delete form.dataset.submitting;
    form.querySelectorAll('button[disabled].opacity-60').forEach((button) => {
        button.disabled = false;
        button.classList.remove('opacity-60');
    });
}));

// Toetsenbord open: alleen touch, een tekstveld met focus én een schermhoogte die flink kleiner is
// geworden. Focus alleen is niet genoeg: de terugknop sluit het toetsenbord en de focus blijft staan.
const viewport = window.visualViewport;
if (viewport && window.matchMedia('(pointer: coarse)').matches) {
    const TEXT_FIELD = 'textarea, [contenteditable="true"], input:not([type="checkbox"], [type="radio"], [type="file"], [type="submit"], [type="button"], [type="date"], [type="time"], [type="range"], [type="color"])';
    let fullHeight = viewport.height;
    let width = viewport.width;

    const sync = () => {
        if (viewport.width !== width) { // gedraaid
            width = viewport.width;
            fullHeight = viewport.height;
        }
        fullHeight = Math.max(fullHeight, viewport.height);
        const open = fullHeight - viewport.height > 120 && Boolean(document.activeElement?.matches(TEXT_FIELD));
        document.documentElement.classList.toggle('keyboard-open', open);
    };

    viewport.addEventListener('resize', sync);
    document.addEventListener('focusin', sync);
    document.addEventListener('focusout', () => setTimeout(sync, 50));
}

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

// Sheets wegvegen: x-swipe-dismiss="sheet = null" op het paneel. Omlaag vegen sleept het paneel mee,
// ver genoeg of snel genoeg sluit het. Staat de inhoud niet bovenaan, dan scrollt die gewoon. Met
// touch-events, want alleen die kunnen het scrollen van de browser tegenhouden.
Alpine.directive('swipe-dismiss', (el, { expression }, { evaluateLater, cleanup }) => {
    const START = 6;
    const CLOSE_AT = 0.3;
    const FLICK = 0.5;
    const dismiss = evaluateLater(expression);
    let gesture = null;
    let color = null;

    const backdrop = () => (el.parentElement.matches('[data-popup]') ? el.parentElement : null);

    // Op desktop staan de builderpanelen vast in de pagina en staat Nova midden in beeld.
    const isSheet = () => el.getBoundingClientRect().bottom >= window.innerHeight - 1
        && (backdrop() !== null || getComputedStyle(el).position === 'fixed');

    const isScrolled = (target) => {
        for (let node = target; node; node = node === el ? null : node.parentElement) {
            if (node.scrollTop > 0) return true;
        }
        return false;
    };

    const shade = (progress) => {
        if (backdrop()) backdrop().style.backgroundColor = `color-mix(in srgb, ${color} ${(1 - progress) * 100}%, transparent)`;
    };

    const settle = (close) => {
        const motion = 'motion' in document.documentElement.dataset && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const duration = motion ? 220 : 0;
        const from = el.style.transform;
        el.style.transform = '';
        const animation = el.animate(
            [{ transform: from }, { transform: close ? 'translateY(100%)' : 'translateY(0)' }],
            { duration, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)', fill: 'forwards' },
        );
        if (backdrop()) backdrop().style.transition = `background-color ${duration}ms`;
        shade(close ? 1 : 0);

        animation.finished.then(() => {
            if (close) dismiss();
            Alpine.nextTick(() => {
                animation.cancel();
                backdrop()?.style.removeProperty('background-color');
                backdrop()?.style.removeProperty('transition');
            });
        });
    };

    const start = (event) => {
        gesture = null;
        if (event.touches.length !== 1 || !isSheet()) return;
        if (event.target.closest('input, textarea, [contenteditable="true"]') || isScrolled(event.target)) return;
        const { clientX, clientY } = event.touches[0];
        gesture = { startX: clientX, startY: clientY, lastY: clientY, lastTime: event.timeStamp, speed: 0, offset: 0, dragging: false };
    };

    const move = (event) => {
        if (!gesture) return;
        const { clientX, clientY } = event.touches[0];
        const moveX = clientX - gesture.startX;
        const moveY = clientY - gesture.startY;

        if (!gesture.dragging) {
            if (Math.hypot(moveX, moveY) < START) return;
            if (moveY <= Math.abs(moveX) || !event.cancelable) {
                gesture = null;
                return;
            }
            gesture.dragging = true;
            gesture.height = el.offsetHeight;
            backdrop()?.style.removeProperty('background-color');
            color = backdrop() ? getComputedStyle(backdrop()).backgroundColor : null;
            backdrop()?.style.setProperty('transition', 'none');
        }

        event.preventDefault();
        gesture.speed = (clientY - gesture.lastY) / Math.max(1, event.timeStamp - gesture.lastTime);
        gesture.lastY = clientY;
        gesture.lastTime = event.timeStamp;
        gesture.offset = Math.max(0, moveY);
        el.style.transform = `translateY(${gesture.offset}px)`;
        shade(gesture.offset / gesture.height);
    };

    const end = (event) => {
        if (!gesture?.dragging) {
            gesture = null;
            return;
        }
        const flicked = gesture.speed > FLICK && event.timeStamp - gesture.lastTime < 100;
        const close = event.type === 'touchend' && (flicked || gesture.offset > gesture.height * CLOSE_AT);
        gesture = null;
        settle(close);
    };

    el.addEventListener('touchstart', start, { passive: true });
    el.addEventListener('touchmove', move, { passive: false });
    el.addEventListener('touchend', end);
    el.addEventListener('touchcancel', end);

    cleanup(() => {
        el.removeEventListener('touchstart', start);
        el.removeEventListener('touchmove', move);
        el.removeEventListener('touchend', end);
        el.removeEventListener('touchcancel', end);
    });
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

Alpine.start();
