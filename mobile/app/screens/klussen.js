// Mijn klussen (jobs.index) en een klus (jobs.show): de werkweergave voor op de bouwplaats. Status (onderweg,
// start na de veiligheidscheck, pauze, hervat), stappen afvinken met foto en notitie, meldingen en afronden
// (levert de werkbon op). Gegevens: api/app/klussen (Api\App\JobController); de acties gaan via dezelfde
// controllers als de website. Template: klussen.html.

const API = import.meta.env.VITE_RENOVION_API;

export const routes = {
    screens: ['klussen'],
    details: { klus: /^\/klussen\/(\d+)$/ },
    parents: { klussen: 'planning', klus: 'planning' },
    sections: { klussen: 'uitvoering', klus: 'uitvoering' },
    paths: { '/klussen': 'klussen' },
    // ?medewerker= (meekijken bij een collega) en ?tab= (bijv. vanuit de Inbox: ?tab=meldingen).
    query: ['klussen', 'klus'],
};

export default function register({ Alpine, api, screen, fetchedAt, errorText, shrinkPhoto }) {
    const os = () => Alpine.store('os');

    Alpine.data('jobs', () => {
        const start = os().query?.medewerker ?? '';
        const base = screen(start ? `klussen:${start}` : 'klussen', start ? `klussen?medewerker=${start}` : 'klussen');

        return {
            ...base,
            subject: start,
            sections: [['vandaag', 'Vandaag'], ['komend', 'Komende klussen'], ['afgerond', 'Afgerond']],
            accent: (status) => ({ gepland: 'bg-gray-300', onderweg: 'bg-steel-500', bezig: 'bg-brand-500', pauze: 'bg-amber-500', klaar: 'bg-green-500' })[status.value],
            workAs(id) {
                this.subject = id;
                const self = !id || Number(id) === os().profile?.user?.id;
                return this.show(self ? 'klussen' : `klussen:${id}`, self ? 'klussen' : `klussen?medewerker=${id}`);
            },
        };
    });

    Alpine.data('job', (id) => {
        const base = screen(`klus:${id}`, `klussen/${id}`);

        return {
            ...base,
            tab: ['stappen', 'info', 'fotos', 'meldingen'].includes(os().query?.tab) ? os().query.tab : 'stappen',
            tabs: [['stappen', 'Stappen'], ['info', 'Info'], ['fotos', "Foto's"], ['meldingen', 'Meldingen']],
            busy: null,
            sheet: null,
            lmra: [],
            finish: null,
            report: null,
            expanded: {},
            noteFor: null,
            noteText: '',
            resolutions: {},
            uploads: [],
            viewer: null,

            init() {
                this.$watch('$store.os.offline', (offline) => !offline && this.retryUploads());
                return base.init.call(this);
            },
            destroy() {
                this.uploads.forEach((upload) => URL.revokeObjectURL(upload.preview));
                this.report?.previews.forEach((preview) => URL.revokeObjectURL(preview));
            },

            photoUrl: (photo) => `${API}${photo.url}`,
            isOpen(step) {
                return this.expanded[step.id] ?? (step.id === this.data?.open_step && !step.done);
            },
            uploadsFor(stepId) {
                return this.uploads.filter((upload) => upload.stepId === stepId);
            },
            get canWork() {
                return Boolean(this.data?.can_execute && this.data?.active);
            },

            // Na elke actie: de klus vers, en de lijsten die hem tonen bij openen opnieuw.
            async refresh() {
                fetchedAt.klussen = fetchedAt.vandaag = fetchedAt.planning = 0;
                Object.keys(fetchedAt).filter((name) => name.startsWith('klussen:')).forEach((name) => { fetchedAt[name] = 0; });
                await this.load({ force: true });
            },
            async run(key, path, options, fallback) {
                if (this.busy) return null;
                this.busy = key;
                let result = null;
                try {
                    result = await api(path, options);
                    await this.refresh();
                    if (result?.message) os().toast(result.message);
                } catch (error) {
                    os().toast(errorText(error, fallback));
                }
                this.busy = null;
                return result;
            },

            // De grote knop: onderweg, start (eerst de veiligheidscheck), pauze of hervat.
            primary() {
                const action = this.data.action;
                if (action.lmra) {
                    this.lmra = this.data.lmra.map(() => false);
                    this.sheet = 'lmra';
                    return;
                }
                this.run('status', `klussen/${this.data.job_id}/status`, { method: 'PATCH', body: { status: action.next } }, 'Status wijzigen lukte niet. Controleer de verbinding.');
            },
            async startWork() {
                if (this.lmra.some((answer) => !answer)) return;
                const result = await this.run('status', `klussen/${this.data.job_id}/status`, { method: 'PATCH', body: { status: 'bezig', lmra: this.lmra } }, 'Starten lukte niet. Controleer de verbinding.');
                if (result) this.sheet = null;
            },

            toggleStep(step) {
                if (!step.done && step.needs_photo) {
                    os().toast('Bij deze stap hoort een foto. Maak die eerst.');
                    return;
                }
                this.run(`step:${step.id}`, `klussen/${this.data.job_id}/stappen/${step.id}/toggle`, { method: 'PATCH' }, 'Afvinken lukte niet. Controleer de verbinding.');
            },
            editNote(step) {
                this.noteFor = this.noteFor === step.id ? null : step.id;
                this.noteText = step.note ?? '';
            },
            async saveNote(step) {
                const result = await this.run(`note:${step.id}`, `klussen/${this.data.job_id}/stappen/${step.id}/notitie`, { method: 'PATCH', body: { note: this.noteText.trim() || null } }, 'Notitie opslaan lukte niet. Controleer de verbinding.');
                if (result) this.noteFor = null;
            },

            // Foto bij een stap: eerst verkleinen. Komt hij door slecht bereik niet aan, dan blijft hij staan met
            // "Opnieuw" en gaat hij vanzelf als de verbinding terug is (zolang de app open blijft).
            async addPhotos(event, step) {
                const files = [...event.target.files];
                event.target.value = '';
                if (!files.length) return;
                const upload = { key: `${Date.now()}-${Math.random()}`, stepId: step.id, jobId: this.data.job_id, preview: URL.createObjectURL(files[0]), count: files.length, files: [], state: 'busy' };
                this.uploads.push(upload);
                upload.files = await Promise.all(files.map(shrinkPhoto));
                await this.send(upload);
            },
            async send(upload) {
                upload.state = 'busy';
                const body = new FormData();
                upload.files.forEach((file) => body.append('photos[]', file));
                try {
                    const result = await api(`klussen/${upload.jobId}/stappen/${upload.stepId}/fotos`, { method: 'POST', body });
                    this.drop(upload);
                    await this.refresh();
                    os().toast(result.message);
                } catch (error) {
                    if (error?.status) {
                        // De server weigert (te groot, geen afbeelding): opnieuw proberen heeft geen zin.
                        this.drop(upload);
                        os().toast(errorText(error, 'Uploaden lukte niet.'));
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

            openFinish() {
                this.finish = { hours: this.data.finish.hours, signed_name: '', materials: '', notes: '' };
                this.sheet = 'finish';
            },
            async submitFinish() {
                if (this.data.finish.missing_evidence.length) return;
                const body = Object.fromEntries(Object.entries(this.finish).filter(([, value]) => value !== '' && value !== null));
                const result = await this.run('finish', `klussen/${this.data.job_id}/afronden`, { method: 'POST', body }, 'Afronden lukte niet. Controleer de verbinding.');
                if (result) {
                    this.sheet = null;
                    fetchedAt.werkbonnen = 0;
                }
            },

            openReport() {
                this.report = { type: 'probleem', body: '', urgent: false, files: [], previews: [] };
                this.sheet = 'report';
            },
            async reportPhotos(event) {
                const files = [...event.target.files];
                event.target.value = '';
                if (!files.length) return;
                this.report.previews.push(...files.map((file) => URL.createObjectURL(file)));
                this.report.files.push(...(await Promise.all(files.map(shrinkPhoto))));
            },
            async submitReport() {
                if (!this.report.body.trim()) return;
                const body = new FormData();
                body.append('type', this.report.type);
                body.append('body', this.report.body.trim());
                body.append('urgent', this.report.urgent ? '1' : '0');
                this.report.files.slice(0, 10).forEach((file) => body.append('photos[]', file));
                const result = await this.run('report', `klussen/${this.data.job_id}/meldingen`, { method: 'POST', body }, 'Melding versturen lukte niet. Controleer de verbinding.');
                if (result) {
                    this.report.previews.forEach((preview) => URL.revokeObjectURL(preview));
                    this.report = null;
                    this.sheet = null;
                    fetchedAt.inbox = 0;
                }
            },
            async resolve(report) {
                const result = await this.run(`resolve:${report.id}`, `klusmeldingen/${report.id}/afhandelen`, { method: 'POST', body: { resolution: (this.resolutions[report.id] ?? '').trim() || null } }, 'Afhandelen lukte niet. Controleer de verbinding.');
                if (result) {
                    delete this.resolutions[report.id];
                    fetchedAt.inbox = 0;
                }
            },
        };
    });
}
