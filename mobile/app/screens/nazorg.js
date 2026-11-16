// Nazorg en service (service.index, alleen projectleiding): meldingen na de oplevering, met de eerste vraag
// of het onder garantie valt. Melding aannemen en bijwerken gaan via de ServiceTicketController van de
// website. Gegevens: api/app/nazorg (Api\App\ServiceTicketController). Template: nazorg.html.

export const routes = {
    screens: ['nazorg'],
    details: {},
    parents: { nazorg: 'projecten' },
    sections: { nazorg: 'projecten' },
    paths: { '/nazorg': 'nazorg' },
    // ?status= (filter)
    query: ['nazorg'],
};

export default function register({ Alpine, api, screen, fetchedAt, errorText }) {
    const os = () => Alpine.store('os');
    const emptyTicket = () => ({ project_id: '', customer_id: '', title: '', description: '', priority: 'normaal', reported_via: 'telefoon' });

    Alpine.data('service', () => {
        const start = os().query?.status ?? '';

        return {
            ...screen(start ? `nazorg:${start}` : 'nazorg', start ? `nazorg?status=${encodeURIComponent(start)}` : 'nazorg'),
            busy: null,
            form: null,
            edit: null,
            editing: {},

            filter(value) {
                return this.show(value ? `nazorg:${value}` : 'nazorg', value ? `nazorg?status=${encodeURIComponent(value)}` : 'nazorg');
            },
            async changed(result) {
                Object.keys(fetchedAt).filter((name) => name === 'nazorg' || name.startsWith('nazorg:')).forEach((name) => { fetchedAt[name] = 0; });
                fetchedAt.inbox = fetchedAt.vandaag = 0;
                await this.load({ force: true });
                if (result?.message) os().toast(result.message);
            },

            add() {
                this.form = emptyTicket();
            },
            async save() {
                if (this.busy) return;
                this.busy = 'store';
                // Met een project komt de klant van het project (het klantveld is dan verborgen).
                const fields = this.form.project_id ? { ...this.form, customer_id: '' } : this.form;
                const body = Object.fromEntries(Object.entries(fields).filter(([, value]) => value !== ''));
                try {
                    const result = await api('nazorg', { method: 'POST', body });
                    this.form = null;
                    await this.changed(result);
                } catch (error) {
                    os().toast(errorText(error, 'Aannemen lukte niet. Controleer de verbinding.'));
                }
                this.busy = null;
            },

            // Bijwerken per melding: open het formulier met de huidige waarden.
            open(ticket) {
                this.edit = this.edit === ticket.id ? null : ticket.id;
                this.editing = { ...ticket.form };
            },
            async update(ticket) {
                if (this.busy) return;
                this.busy = `update:${ticket.id}`;
                const body = {
                    ...this.editing,
                    assigned_to: this.editing.assigned_to || null,
                    planned_on: this.editing.planned_on || null,
                    resolution: this.editing.resolution?.trim() || null,
                };
                try {
                    const result = await api(`nazorg/${ticket.id}`, { method: 'PATCH', body });
                    this.edit = null;
                    await this.changed(result);
                } catch (error) {
                    os().toast(errorText(error, 'Opslaan lukte niet. Controleer de verbinding.'));
                }
                this.busy = null;
            },
        };
    });
}
