// Gespreksverslagen (Nova notuleert): de lijst, opnemen en het verslag, met dezelfde gegevens en acties als
// meetings/index, create en show op de website. Opnemen gebruikt dezelfde recorder als de website
// (resources/js/meeting-recorder.js), maar dan via api/app met het token van de app.
// Gegevens: api/app/verslagen* (Api\App\MeetingController, acties via MeetingController). Template: verslagen.html.
import { meetingRecorder } from '../../../resources/js/meeting-recorder.js';

const API = import.meta.env.VITE_RENOVION_API;

export const routes = {
    screens: ['verslagen', 'opnemen'],
    details: { verslag: /^\/verslagen\/(\d+)$/ },
    parents: { verslag: 'verslagen', opnemen: 'verslagen' },
    sections: { verslagen: 'uitvoering', verslag: 'uitvoering', opnemen: 'uitvoering' },
    paths: { '/verslagen': 'verslagen', '/verslagen/opnemen': 'opnemen' },
    // Opnemen vanaf een project: /verslagen/opnemen?project=5 => #/opnemen?project=5
    query: ['opnemen'],
};

export default function register({ Alpine, api, screen, fetchedAt, errorText }) {
    const os = () => Alpine.store('os');

    Alpine.data('meetings', () => ({
        ...screen('verslagen', 'verslagen'),
        // Volgende pagina's (30 per keer, zoals de website): niet bewaard, de eerste pagina wel.
        extra: [],
        page: 1,
        more: null,
        loadingMore: false,
        get list() {
            return [...(this.data?.meetings ?? []), ...this.extra];
        },
        get hasMore() {
            return this.more ?? this.data?.more ?? false;
        },
        async loadMore() {
            if (this.loadingMore) return;
            this.loadingMore = true;
            try {
                const next = await api(`verslagen?page=${this.page + 1}`);
                const known = new Set(this.list.map((meeting) => meeting.id));
                this.extra = [...this.extra, ...next.meetings.filter((meeting) => !known.has(meeting.id))];
                this.page = next.page;
                this.more = next.more;
            } catch (error) {
                os().toast(errorText(error, 'Meer laden lukte niet. Controleer de verbinding.'));
            }
            this.loadingMore = false;
        },
    }));

    // Opnemen: het formulier komt van de server (projecten, soorten), de opname zelf doet de recorder van de
    // website. Weg van dit scherm tijdens de opname = stoppen en het verslag laten maken (een hashwissel kan
    // de app niet tegenhouden zoals beforeunload op de website).
    Alpine.data('meetingRecord', () => {
        const base = screen('opnemen', 'verslagen/opnemen');
        let left = false;
        const transport = {
            start: (body) => api('verslagen', { method: 'POST', body }),
            async chunk(meeting, body) {
                try {
                    return { ok: true, status: 200, result: await api(`verslagen/${meeting.id}/geluid`, { method: 'POST', body }) };
                } catch (error) {
                    if (!error.status) throw error; // geen verbinding: de recorder wacht en probeert opnieuw
                    return { ok: false, status: error.status, result: error.data ?? {}, retryAfter: 0 };
                }
            },
            stop: (meeting, duration) => api(`verslagen/${meeting.id}/stoppen`, { method: 'POST', body: { duration } }),
            done(result, meeting) {
                fetchedAt.verslagen = 0;
                if (left) os().toast('Opname gestopt. Nova maakt het verslag.');
                else location.replace(`#/verslag/${meeting.id}`);
            },
            errors: (error) => error.data?.errors,
        };
        const recorder = meetingRecorder({ storeUrl: null, project: os().query?.project ?? '', kind: 'intern', transport });
        // Samenvoegen met de getters erbij (time): {...recorder} zou die één keer uitrekenen.
        const component = Object.defineProperties({}, { ...Object.getOwnPropertyDescriptors(base), ...Object.getOwnPropertyDescriptors(recorder) });

        return Object.assign(component, {
            init() {
                recorder.init.call(this);
                return base.init.call(this);
            },
            destroy() {
                if (this.step !== 'recording' && this.step !== 'stopping') {
                    recorder.destroy.call(this);
                    return;
                }
                left = true;
                window.removeEventListener('beforeunload', this.onLeave);
                document.removeEventListener('visibilitychange', this.onVisible);
                if (this.step === 'recording') this.stop();
            },
        });
    });

    Alpine.data('meeting', (id) => {
        const base = screen(`verslag:${id}`, `verslagen/${id}`);

        return {
            ...base,
            busy: null,
            form: [],
            formKey: null,
            channel: '',
            transcriptOpen: false,
            timer: null,
            audioUrl: (meeting) => `${API}${meeting.audio.url}`,

            init() {
                // Acties als formulier (aanvinken, titel, eigenaar, datum), opnieuw als de acties op de server anders zijn.
                this.$watch('data', () => this.prepare());
                // Nog bezig: elke 5 s de status (dat helpt de verwerking ook een stap verder), dan het verslag opnieuw.
                this.timer = setInterval(() => this.poll(), 5000);
                const started = base.init.call(this);
                this.prepare();
                return started;
            },
            destroy() {
                clearInterval(this.timer);
            },
            prepare() {
                if (!this.data) return;
                const key = JSON.stringify(this.data.actions);
                if (key !== this.formKey) {
                    this.formKey = key;
                    this.form = this.data.actions.map((action) => ({ selected: true, title: action.title, owner_id: action.owner_id ? String(action.owner_id) : '', deadline: action.due ?? '' }));
                }
                if (!this.channel || !this.data.channels.some((channel) => String(channel.id) === this.channel)) {
                    const chosen = this.data.channels.find((channel) => channel.selected) ?? this.data.channels[0];
                    this.channel = chosen ? String(chosen.id) : '';
                }
            },
            get openActions() {
                return (this.data?.actions ?? []).some((action) => action.task_id === null);
            },
            async poll() {
                if (this.data?.status.value !== 'verwerken' || document.visibilityState !== 'visible') return;
                try {
                    const { status } = await api(`verslagen/${id}/status`);
                    if (status !== 'verwerken') await this.changed();
                } catch { /* de volgende keer weer */ }
            },
            async changed() {
                fetchedAt.verslagen = 0;
                await this.load({ force: true });
            },
            async run(name, path, body, fallback, method = 'POST') {
                if (this.busy) return null;
                this.busy = name;
                let result = null;
                try {
                    result = await api(path, { method, body });
                    if (result?.message) os().toast(result.message);
                } catch (error) {
                    os().toast(errorText(error, fallback));
                }
                this.busy = null;
                return result;
            },
            async stopNow() {
                if (await this.run('stop', `verslagen/${id}/stoppen`, {}, 'Verwerken starten lukte niet. Controleer de verbinding.')) await this.changed();
            },
            async retry() {
                if (await this.run('retry', `verslagen/${id}/opnieuw`, {}, 'Opnieuw proberen lukte niet. Controleer de verbinding.')) await this.changed();
            },
            async createTasks() {
                const actions = this.data.actions.map((action, index) => (action.task_id
                    ? { title: action.title }
                    : { selected: this.form[index].selected, title: this.form[index].title.trim(), owner_id: this.form[index].owner_id || null, deadline: this.form[index].deadline || null }));
                if (await this.run('tasks', `verslagen/${id}/taken`, { actions }, 'Taken maken lukte niet. Controleer de verbinding.')) {
                    fetchedAt.taken = fetchedAt.vandaag = 0;
                    this.formKey = null;
                    await this.changed();
                }
            },
            async share() {
                if (!this.channel) return;
                if (await this.run('share', `verslagen/${id}/delen`, { channel_id: Number(this.channel) }, 'Delen lukte niet. Controleer de verbinding.')) fetchedAt.chat = 0;
            },
            async remove() {
                if (!(await os().ask('Verslag en opname definitief verwijderen?', 'Verwijderen'))) return;
                if (await this.run('delete', `verslagen/${id}`, undefined, 'Verwijderen lukte niet. Controleer de verbinding.', 'DELETE')) {
                    fetchedAt.verslagen = 0;
                    location.replace('#/verslagen');
                }
            },
            // Opnames uit de browser (webm) hebben geen lengte in het bestand: één keer naar het eind springen
            // laat de webview de lengte uitrekenen, zodat de schuifbalk werkt (zoals op de website).
            fixDuration(el) {
                if (el.duration !== Infinity) return;
                el.currentTime = 1e101;
                el.addEventListener('timeupdate', () => { el.currentTime = 0; }, { once: true });
            },
            back() {
                if (history.length > 1) history.back();
                else location.hash = '#/verslagen';
            },
        };
    });
}
