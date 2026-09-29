import Alpine from 'alpinejs';

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

    init() {
        this.lastId = this.messages.length ? this.messages[this.messages.length - 1].id : 0;
        this.scrollDown();
        this.pollTimer = setInterval(() => this.poll(), 8000);
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
