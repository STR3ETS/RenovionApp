// Prijsaanvragen: werk uitzetten bij onderaannemers, biedingen vergelijken tegen de begroting en gunnen.
// Lijst: api/app/prijsaanvragen (verplichtingen, statustabs, projectfilter). Aanvraag: api/app/prijsaanvragen/{id}
// met gunnen, bieding vastleggen, partijen uitnodigen en de gegevens opslaan (Api\App\RfqController en de
// RfqController van de website). Template: prijsaanvragen.html. Een nieuwe aanvraag is het formulier op /prijsaanvragen/nieuw.

export const routes = {
    screens: ['prijsaanvragen'],
    details: { prijsaanvraag: /^\/prijsaanvragen\/(\d+)$/ },
    parents: { prijsaanvraag: 'prijsaanvragen' },
    sections: { prijsaanvragen: 'mensen', prijsaanvraag: 'mensen' },
    paths: { '/prijsaanvragen': 'prijsaanvragen' },
};

// Na een wijziging: de lijst (met elk filter) bij openen opnieuw.
function listsStale(fetchedAt) {
    Object.keys(fetchedAt).filter((name) => name.startsWith('prijsaanvragen')).forEach((name) => { fetchedAt[name] = 0; });
    fetchedAt.taken = fetchedAt.vandaag = 0;
}

export default function register({ Alpine, api, screen, fetchedAt, cache, errorText }) {
    Alpine.data('rfqs', () => ({
        ...screen('prijsaanvragen', 'prijsaanvragen'),
        status: '',
        project: '',
        filter() {
            const query = new URLSearchParams(Object.entries({ status: this.status, project: this.project }).filter(([, value]) => value));
            const suffix = query.toString();
            return this.show(suffix ? `prijsaanvragen:${suffix}` : 'prijsaanvragen', suffix ? `prijsaanvragen?${suffix}` : 'prijsaanvragen');
        },
        setStatus(value) {
            if (this.status === value) return;
            this.status = value;
            this.filter();
        },
    }));

    Alpine.data('rfq', (id) => ({
        ...screen(`prijsaanvraag:${id}`, `prijsaanvragen/${id}`),
        busy: null,
        bid: { subcontractor_id: '', price: '', can_start_on: '', completeness: 100, conditions: '' },
        invite: [],
        editing: false,
        form: { budget: '', deadline: '', starts_on: '', scope: '' },
        async changed(message) {
            listsStale(fetchedAt);
            await this.load({ force: true });
            if (message) Alpine.store('os').toast(message);
        },
        async award(bid) {
            if (this.busy) return;
            if (!(await Alpine.store('os').ask(`Gunnen aan ${bid.partner} voor ${window.osFormat.euro(bid.price)}? Het bedrag wordt een verplichting op het project.`, 'Gunnen'))) return;
            this.busy = `award:${bid.id}`;
            try {
                const { message } = await api(`prijsaanvragen/${id}/gunnen/${bid.id}`, { method: 'POST' });
                await this.changed(message);
            } catch (error) {
                Alpine.store('os').toast(errorText(error, 'Gunnen lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
        async saveBid() {
            if (this.busy) return;
            const body = { ...this.bid, subcontractor_id: this.bid.subcontractor_id || this.data.invited[0]?.id };
            if (!body.subcontractor_id || body.price === '') return;
            this.busy = 'bid';
            try {
                const { message } = await api(`prijsaanvragen/${id}/biedingen`, { method: 'POST', body });
                this.bid = { subcontractor_id: '', price: '', can_start_on: '', completeness: 100, conditions: '' };
                await this.changed(message);
            } catch (error) {
                Alpine.store('os').toast(errorText(error, 'Bieding vastleggen lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
        toggleInvite(partnerId) {
            this.invite = this.invite.includes(partnerId) ? this.invite.filter((other) => other !== partnerId) : [...this.invite, partnerId];
        },
        async sendInvites() {
            if (this.busy || this.invite.length === 0) return;
            this.busy = 'invite';
            try {
                const { message } = await api(`prijsaanvragen/${id}/uitnodigen`, { method: 'POST', body: { subcontractor_ids: this.invite } });
                this.invite = [];
                await this.changed(message);
            } catch (error) {
                Alpine.store('os').toast(errorText(error, 'Uitnodigen lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
        startEdit() {
            const { budget, deadline, starts_on: startsOn, scope } = this.data.form;
            this.form = { budget: budget ?? '', deadline: deadline ?? '', starts_on: startsOn ?? '', scope: scope ?? '' };
            this.editing = true;
        },
        async save() {
            if (this.busy) return;
            this.busy = 'save';
            try {
                // Leeg budget niet meesturen: de website eist een getal als het veld er is.
                const body = { deadline: this.form.deadline || null, starts_on: this.form.starts_on || null, scope: this.form.scope || null };
                if (this.form.budget !== '' && this.form.budget !== null) body.budget = this.form.budget;
                const { message, ...fresh } = await api(`prijsaanvragen/${id}`, { method: 'PATCH', body });
                cache.write(this.cacheName, fresh, JSON.stringify(fresh));
                this.present(cache.read(this.cacheName));
                listsStale(fetchedAt);
                this.editing = false;
                Alpine.store('os').toast(message);
            } catch (error) {
                Alpine.store('os').toast(errorText(error, 'Opslaan lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
    }));
}
