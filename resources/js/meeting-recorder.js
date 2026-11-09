// Gesprek opnemen (Nova notuleert): de microfoon neemt op en stuurt elke paar seconden een stukje geluid
// naar de server. Valt de verbinding weg, dan wachten de stukjes en gaan ze op volgorde zodra het weer
// kan; zo kost een weggevallen netwerk of lege accu hooguit het laatste stukje. Het scherm blijft aan
// zolang er wordt opgenomen (Wake Lock), want een vergrendelde telefoon stopt de microfoon.
const CHUNK_MS = 5000;
const TYPES = ['audio/webm;codecs=opus', 'audio/mp4', 'audio/ogg;codecs=opus', 'audio/webm'];

const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

export default (Alpine) => Alpine.data('meetingRecorder', ({ storeUrl, project = '', kind = 'intern' }) => {
    // Niet reactief: Alpine hoeft de recorder, de stream en de wachtrij niet te volgen.
    let recorder = null;
    let stream = null;
    let audio = null;
    let wakeLock = null;
    let queue = [];
    let nextSeq = 0;
    let sending = false;
    let urls = null;
    let startedAt = 0;
    let pausedTotal = 0;
    let pausedAt = 0;
    let ticker = null;
    let meter = null;

    return {
        step: 'form',
        title: '',
        kind,
        projectId: project,
        consent: false,
        error: null,
        busy: false,
        seconds: 0,
        paused: false,
        pending: 0,
        offline: false,
        levels: Array(18).fill(3),

        get time() {
            const s = this.seconds;
            const pad = (n) => String(n).padStart(2, '0');
            return s >= 3600 ? `${Math.floor(s / 3600)}:${pad(Math.floor(s / 60) % 60)}:${pad(s % 60)}` : `${pad(Math.floor(s / 60))}:${pad(s % 60)}`;
        },

        init() {
            this.onLeave = (event) => {
                if (this.step === 'recording' || this.step === 'stopping') {
                    event.preventDefault();
                    event.returnValue = '';
                }
            };
            this.onVisible = () => document.visibilityState === 'visible' && this.step === 'recording' && this.keepAwake();
            window.addEventListener('beforeunload', this.onLeave);
            document.addEventListener('visibilitychange', this.onVisible);
        },

        destroy() {
            window.removeEventListener('beforeunload', this.onLeave);
            document.removeEventListener('visibilitychange', this.onVisible);
            this.release();
        },

        async start() {
            this.error = null;
            if (!this.title.trim()) {
                this.error = 'Geef het gesprek een onderwerp.';
                return;
            }
            if (!this.consent) {
                this.error = 'Bevestig dat iedereen weet dat het gesprek wordt opgenomen.';
                return;
            }
            // De microfoon mag alleen over https (of localhost/127.0.0.1); de testserver via het wifi-adres is http.
            if (!window.isSecureContext) {
                this.error = 'De microfoon werkt alleen via een beveiligde verbinding (https). Deze server is http, op de live omgeving werkt het wel.';
                return;
            }
            if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
                this.error = 'Opnemen werkt niet in deze browser. Gebruik de Renovion-app of Chrome.';
                return;
            }

            this.busy = true;
            try {
                stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true } });
            } catch {
                this.busy = false;
                this.error = 'Geen toegang tot de microfoon. Sta de microfoon toe en probeer het opnieuw.';
                return;
            }

            const mimeType = TYPES.find((type) => MediaRecorder.isTypeSupported(type)) ?? '';
            try {
                urls = await window.postJson(storeUrl, { title: this.title.trim(), kind: this.kind, project_id: this.projectId || null, consent: 1, mime: mimeType });
            } catch (error) {
                this.release();
                this.busy = false;
                this.error = error.body?.errors ? Object.values(error.body.errors)[0][0] : 'Starten lukte niet. Controleer de verbinding.';
                return;
            }

            // 16 kbps is ruim voor spraak, en zo past 3 uur onder de 25 MB die Whisper per bestand aanneemt.
            recorder = new MediaRecorder(stream, { ...(mimeType ? { mimeType } : {}), audioBitsPerSecond: 16000 });
            recorder.ondataavailable = (event) => {
                if (!event.data?.size) return;
                queue.push({ seq: nextSeq++, blob: event.data });
                this.pending = queue.length;
                this.send();
            };
            recorder.start(CHUNK_MS);
            startedAt = Date.now();
            ticker = setInterval(() => {
                if (!this.paused) this.seconds = Math.floor((Date.now() - startedAt - pausedTotal) / 1000);
            }, 500);
            this.listen();
            this.keepAwake();
            this.busy = false;
            this.step = 'recording';
        },

        pause() {
            if (!recorder) return;
            if (this.paused) {
                recorder.resume();
                pausedTotal += Date.now() - pausedAt;
            } else {
                recorder.pause();
                pausedAt = Date.now();
            }
            this.paused = !this.paused;
        },

        async stop() {
            if (!recorder || this.step !== 'recording') return;
            if (this.paused) this.pause();
            this.step = 'stopping';
            await new Promise((resolve) => {
                recorder.addEventListener('stop', resolve, { once: true });
                recorder.stop();
            });
            const duration = this.seconds;
            this.release();
            // Eerst alles binnen, dan pas stoppen: anders mist het verslag het laatste stuk.
            while (queue.length || sending) await wait(300);
            for (let attempt = 0; ; attempt++) {
                try {
                    const { url } = await window.postJson(urls.stop_url, { duration });
                    this.step = 'done';
                    location.href = url;
                    return;
                } catch {
                    this.offline = true;
                    await wait(Math.min(15000, 2000 * (attempt + 1)));
                }
            }
        },

        // Stukjes op volgorde versturen; bij geen verbinding blijven ze staan en probeert hij het opnieuw.
        // Een tijdelijke weigering (te veel tegelijk na een tijd offline, serverfout, verlopen sessie) is ook
        // geen reden om geluid weg te gooien: dat stukje blijft vooraan staan tot het lukt. Alleen een
        // definitief nee (opname al gestopt of te lang, 422) laat het stukje vallen.
        async send() {
            if (sending) return;
            sending = true;
            let attempt = 0;
            while (queue.length) {
                const chunk = queue[0];
                const body = new FormData();
                body.append('seq', chunk.seq);
                body.append('chunk', chunk.blob, `stuk-${chunk.seq}`);
                let retry = false;
                try {
                    const response = await fetch(urls.chunk_url, { method: 'POST', body, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, credentials: 'same-origin' });
                    const result = await response.json().catch(() => ({}));
                    if (response.ok) {
                        queue.shift();
                        attempt = 0;
                        this.error = null;
                    } else if (response.status === 409) {
                        // De server mist stukjes die er niet meer zijn: nummer de rest vanaf wat hij verwacht.
                        queue.forEach((item, index) => { item.seq = result.received + index; });
                        nextSeq = result.received + queue.length;
                    } else if (response.status === 422 || response.status === 403 || response.status === 413) {
                        this.error = result.message ?? 'Een stukje geluid werd geweigerd.';
                        queue.shift();
                    } else {
                        // 429, 419, 5xx: tijdelijk. Even wachten (bij 429 zo lang als de server vraagt) en opnieuw.
                        retry = true;
                        if (response.status === 419) this.error = 'Je sessie is verlopen. De opname loopt door; log in een ander tabblad opnieuw in, dan gaat het geluid vanzelf mee.';
                        const after = Number(response.headers.get('Retry-After')) * 1000;
                        await wait(after > 0 ? Math.min(after, 60000) : Math.min(15000, 1500 * ++attempt));
                    }
                    this.offline = false;
                } catch {
                    retry = true;
                    this.offline = true;
                    await wait(Math.min(15000, 1500 * ++attempt));
                }
                if (!retry) attempt = 0;
                this.pending = queue.length;
            }
            sending = false;
        },

        // Geluidsniveau als balkjes, zodat je ziet dat de microfoon iets hoort.
        listen() {
            const Context = window.AudioContext || window.webkitAudioContext;
            if (!Context) return;
            audio = new Context();
            const analyser = audio.createAnalyser();
            analyser.fftSize = 64;
            audio.createMediaStreamSource(stream).connect(analyser);
            const data = new Uint8Array(analyser.frequencyBinCount);
            meter = setInterval(() => {
                analyser.getByteFrequencyData(data);
                this.levels = this.levels.map((_, index) => (this.paused ? 3 : Math.max(3, Math.min(28, data[index + 1] / 8))));
            }, 120);
        },

        async keepAwake() {
            try {
                wakeLock = await navigator.wakeLock?.request('screen');
            } catch { /* niet ondersteund of geweigerd: dan kan het scherm uit */ }
        },

        release() {
            clearInterval(ticker);
            clearInterval(meter);
            stream?.getTracks().forEach((track) => track.stop());
            audio?.close().catch(() => {});
            wakeLock?.release().catch(() => {});
            stream = audio = wakeLock = null;
        },
    };
});
