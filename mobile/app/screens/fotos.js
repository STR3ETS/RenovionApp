// Foto's: de Foto's-tab van een project (projects/tabs/fotos: filter per fase, uploaden met fase, omschrijving
// en "zichtbaar voor de klant") als #/fotos/{project}, en één foto schermvullend (photos.show) als #/foto/{id},
// met vorige/volgende, verwijderen en voor kantoor omschrijving en klantzichtbaarheid.
// Gegevens: api/app/projecten/{id}/fotos en api/app/fotos/{id}/gegevens (Api\App\PhotoController); uploaden en
// bijwerken via PhotoController van de website (JSON), verwijderen via api.app.photos.destroy. Template: fotos.html.
const API = import.meta.env.VITE_RENOVION_API;

export const routes = {
    // fotos: geen eigen serverpagina (de tab staat op het project), dus een pad dat nooit als pagina opent.
    details: { foto: /^\/fotos\/(\d+)$/, fotos: /^\/projecten\/(\d+)\/fotos$/ },
    parents: { foto: 'projecten', fotos: 'projecten' },
    sections: { foto: 'projecten', fotos: 'projecten' },
    query: ['fotos'],
    // De Foto's-tab van het projectdossier (/projecten/5?tab=fotos, ook met &fase=12) => #/fotos/5?fase=12
    rewrites: [(pathname, params) => {
        const match = pathname.match(/^\/projecten\/(\d+)$/);
        if (!match || params.get('tab') !== 'fotos') return null;
        return `#/fotos/${match[1]}${/^\d+$/.test(params.get('fase') ?? '') ? `?fase=${params.get('fase')}` : ''}`;
    }],
};

export default function register({ Alpine, api, screen, fetchedAt, errorText, shrinkPhoto }) {
    const os = () => Alpine.store('os');
    const photoUrl = (photo) => `${API}${photo.url}`;

    Alpine.data('projectPhotos', (id) => {
        const base = screen(`fotos:${id}`, `projecten/${id}/fotos`);

        return {
            ...base,
            phase: /^\d+$/.test(os().query?.fase ?? '') ? Number(os().query.fase) : null,
            uploadPhase: '',
            caption: '',
            clientVisible: true,
            uploads: [],
            photoUrl,

            init() {
                this.$watch('$store.os.offline', (offline) => !offline && this.retryUploads());
                // De huidige fase staat klaar bij uploaden, zoals op de website.
                this.$watch('data', () => { if (this.uploadPhase === '' && this.data?.current_phase_id) this.uploadPhase = String(this.data.current_phase_id); });
                const started = base.init.call(this);
                if (this.data?.current_phase_id) this.uploadPhase = String(this.data.current_phase_id);
                return started;
            },
            destroy() {
                this.uploads.forEach((upload) => URL.revokeObjectURL(upload.preview));
            },
            get shown() {
                const photos = this.data?.photos ?? [];
                return this.phase ? photos.filter((photo) => photo.phase_id === this.phase) : photos;
            },
            setPhase(value) {
                this.phase = value;
                // In de hash, zodat terug van een foto op hetzelfde filter uitkomt.
                history.replaceState(null, '', `#/fotos/${id}${value ? `?fase=${value}` : ''}`);
            },
            open(photo) {
                location.hash = `#/foto/${photo.id}`;
            },

            async addPhotos(event) {
                const files = [...event.target.files];
                event.target.value = '';
                if (!files.length) return;
                const upload = {
                    key: `${Date.now()}-${Math.random()}`,
                    phase: this.uploadPhase,
                    caption: this.caption.trim() || null,
                    visible: this.clientVisible,
                    preview: URL.createObjectURL(files[0]),
                    count: files.length,
                    files: [],
                    state: 'busy',
                };
                this.caption = '';
                this.uploads.push(upload);
                upload.files = await Promise.all(files.map(shrinkPhoto));
                await this.send(upload);
            },
            async send(upload) {
                upload.state = 'busy';
                const body = new FormData();
                upload.files.forEach((file) => body.append('photos[]', file));
                if (upload.phase) body.append('project_phase_id', upload.phase);
                if (upload.caption) body.append('caption', upload.caption);
                body.append('client_visible', upload.visible ? '1' : '0');
                try {
                    const { message } = await api(`projecten/${id}/fotos`, { method: 'POST', body });
                    this.drop(upload);
                    this.changed();
                    await this.load({ force: true });
                    os().toast(message);
                } catch (error) {
                    if (error.status) {
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
            changed() {
                fetchedAt[`project:${id}`] = fetchedAt.projecten = fetchedAt.vandaag = 0;
            },
            back() {
                if (history.length > 1) history.back();
                else location.hash = `#/project/${id}`;
            },
        };
    });

    // Eén foto schermvullend: vegen of de pijlen voor vorige/volgende in hetzelfde project.
    Alpine.data('photoViewer', (id) => {
        const base = screen(`foto:${id}`, `fotos/${id}/gegevens`);
        let touch = null;

        return {
            ...base,
            busy: null,
            editing: false,
            draft: '',
            photoUrl,

            init() {
                window.dispatchEvent(new Event('renovion-top-changed'));
                return base.init.call(this);
            },
            destroy() {
                setTimeout(() => window.dispatchEvent(new Event('renovion-top-changed')), 50);
            },
            close() {
                if (history.length > 1) history.back();
                else location.hash = this.data ? `#/fotos/${this.data.project.id}` : '#/projecten';
            },
            step(to) {
                if (to) location.replace(`#/foto/${to}`);
            },
            touchStart(event) {
                touch = event.touches.length === 1 ? { x: event.touches[0].clientX, y: event.touches[0].clientY } : null;
            },
            touchEnd(event) {
                if (!touch || !this.data) return;
                const moveX = event.changedTouches[0].clientX - touch.x;
                const moveY = event.changedTouches[0].clientY - touch.y;
                touch = null;
                if (Math.abs(moveX) > 60 && Math.abs(moveX) > Math.abs(moveY)) this.step(moveX < 0 ? this.data.next : this.data.previous);
                else if (moveY > 120) this.close();
            },
            changed() {
                const project = this.data.project.id;
                fetchedAt[`fotos:${project}`] = fetchedAt[`project:${project}`] = fetchedAt.projecten = fetchedAt.vandaag = 0;
            },
            async update(body) {
                if (this.busy) return;
                this.busy = 'update';
                try {
                    await api(`fotos/${id}`, { method: 'PATCH', body });
                    this.changed();
                    this.editing = false;
                    await this.load({ force: true });
                } catch (error) {
                    os().toast(errorText(error, 'Bijwerken lukte niet. Controleer de verbinding.'));
                }
                this.busy = null;
            },
            edit() {
                this.draft = this.data.raw_caption ?? '';
                this.editing = true;
            },
            async remove() {
                if (!(await os().ask('Deze foto verwijderen?', 'Verwijderen'))) return;
                this.busy = 'delete';
                try {
                    await api(`fotos/${id}`, { method: 'DELETE' });
                    this.changed();
                    os().toast('Foto verwijderd.');
                    const next = this.data.next ?? this.data.previous;
                    location.replace(next ? `#/foto/${next}` : `#/fotos/${this.data.project.id}`);
                } catch (error) {
                    os().toast(errorText(error, 'Verwijderen lukte niet. Probeer het opnieuw.'));
                }
                this.busy = null;
            },
        };
    });
}
