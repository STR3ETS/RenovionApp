// Onderaannemers (subcontractors/index) en het partnerdossier (subcontractors/show): zoeken op naam of plaats,
// filteren op vak en status, een partner aanmelden; per partner de dossiereisen, een dossierstuk vastleggen
// (met bestand), gegunde opdrachten, gegevens bewerken en de inlog voor het partnerportaal.
// Gegevens: api/app/onderaannemers (Api\App\SubcontractorController); wijzigingen via de SubcontractorController
// van de website. Template: onderaannemers.html.

export const routes = {
    screens: ['onderaannemers'],
    details: { onderaannemer: /^\/onderaannemers\/(\d+)$/ },
    parents: { onderaannemers: 'team', onderaannemer: 'team' },
    sections: { onderaannemers: 'mensen', onderaannemer: 'mensen' },
    paths: { '/onderaannemers': 'onderaannemers' },
};

const copy = (value) => JSON.parse(JSON.stringify(value));

export default function register({ Alpine, api, screen, fetchedAt, errorText, shrinkPhoto }) {
    const os = () => Alpine.store('os');
    const invalidate = () => Object.keys(fetchedAt).filter((name) => name.startsWith('onderaannemer')).forEach((name) => { fetchedAt[name] = 0; });

    Alpine.data('partners', () => ({
        ...screen('onderaannemers', 'onderaannemers'),
        q: '',
        trade: '',
        status: '',
        sheet: false,
        busy: false,
        formError: null,
        form: { name: '', contact_name: '', email: '', city: '' },
        // Zoeken en vak gelden voor de tellers, de status-tab alleen voor de lijst (zoals op de website).
        get matching() {
            const term = this.q.trim().toLowerCase();
            return (this.data?.subcontractors ?? []).filter((sub) => (!term || sub.name.toLowerCase().includes(term) || (sub.city ?? '').toLowerCase().includes(term))
                && (!this.trade || sub.trades.includes(this.trade)));
        },
        get shown() {
            return this.status ? this.matching.filter((sub) => sub.status.value === this.status) : this.matching;
        },
        count(status) {
            return this.matching.filter((sub) => sub.status.value === status).length;
        },
        open() {
            this.form = { name: '', contact_name: '', email: '', city: '' };
            this.formError = null;
            this.sheet = true;
        },
        async store() {
            if (this.busy) return;
            this.busy = true;
            this.formError = null;
            try {
                const result = await api('onderaannemers', { method: 'POST', body: this.form });
                invalidate();
                this.sheet = false;
                os().toast(result.message);
                os().go(result.path);
            } catch (error) {
                this.formError = errorText(error, 'Aanmelden lukte niet. Controleer de verbinding.');
            }
            this.busy = false;
        },
    }));

    Alpine.data('partner', (id) => {
        const base = screen(`onderaannemer:${id}`, `onderaannemers/${id}`);

        return {
            ...base,
            busy: null,
            form: null,
            dirty: false,
            doc: { type: '', valid_until: '', file: null },
            secret: null,
            copied: false,
            init() {
                // Het formulier volgt de gegevens van de server, tot je zelf iets wijzigt.
                this.$watch('data', (data) => { if (data && !this.dirty) this.form = copy(data.form); });
                if (this.data && !this.form) this.form = copy(this.data.form);
                return base.init.call(this).then(() => {
                    if (this.data && !this.form) this.form = copy(this.data.form);
                    if (this.data && !this.doc.type) this.doc.type = this.data.document_types[0] ?? '';
                });
            },
            toggleTrade(trade) {
                this.dirty = true;
                this.form.trades = this.form.trades.includes(trade) ? this.form.trades.filter((t) => t !== trade) : [...this.form.trades, trade];
            },
            async refresh() {
                invalidate();
                await this.load({ force: true });
            },
            async save() {
                if (this.busy) return;
                this.busy = 'save';
                try {
                    const body = { ...this.form, hourly_rate: this.form.hourly_rate === '' ? null : this.form.hourly_rate, rating: this.form.rating ? Number(this.form.rating) : null };
                    const result = await api(`onderaannemers/${id}`, { method: 'PATCH', body });
                    this.dirty = false;
                    await this.refresh();
                    this.form = copy(this.data.form);
                    os().toast(result.message);
                } catch (error) {
                    os().toast(errorText(error, 'Opslaan lukte niet. Controleer de verbinding.'));
                }
                this.busy = null;
            },
            pick(event) {
                this.doc.file = event.target.files?.[0] ?? null;
            },
            async storeDocument() {
                if (this.busy || !this.doc.type) return;
                this.busy = 'doc';
                try {
                    const body = new FormData();
                    body.append('type', this.doc.type);
                    if (this.doc.valid_until) body.append('valid_until', this.doc.valid_until);
                    if (this.doc.file) body.append('file', this.doc.file.type.startsWith('image/') ? await shrinkPhoto(this.doc.file) : this.doc.file);
                    const result = await api(`onderaannemers/${id}/dossier`, { method: 'POST', body });
                    this.doc = { type: this.doc.type, valid_until: '', file: null };
                    if (this.$refs.file) this.$refs.file.value = '';
                    await this.refresh();
                    os().toast(result.message);
                } catch (error) {
                    os().toast(errorText(error, 'Vastleggen lukte niet. Controleer de verbinding.'));
                }
                this.busy = null;
            },
            async portal() {
                if (this.busy) return;
                const question = this.data.portal
                    ? [`Nieuw wachtwoord instellen voor ${this.data.name}? Het oude werkt dan niet meer.`, 'Nieuw wachtwoord']
                    : [`Inlog voor het partnerportaal aanmaken op ${this.data.email ?? 'het e-mailadres van de partner'}?`, 'Account aanmaken'];
                if (!(await os().ask(...question))) return;
                this.busy = 'portal';
                try {
                    const result = await api(`onderaannemers/${id}/portaal`, { method: 'POST' });
                    this.secret = { email: result.email, password: result.password };
                    await this.refresh();
                } catch (error) {
                    os().toast(errorText(error, 'Dat lukte niet. Controleer de verbinding.'));
                }
                this.busy = null;
            },
            async copySecret() {
                try {
                    await navigator.clipboard.writeText(this.secret.password);
                    this.copied = true;
                    setTimeout(() => { this.copied = false; }, 2000);
                } catch {
                    os().toast('Kopiëren lukte niet.');
                }
            },
        };
    });
}
