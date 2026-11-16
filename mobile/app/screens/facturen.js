// Facturatie (invoices/index) en de factuur (invoices/show): kerncijfers, termijnen die klaarstaan om te
// factureren, de lijst per status en de factuur met versturen, betaald melden en concept verwijderen.
// Gegevens: api/app/facturen (Api\App\InvoiceController); acties via de InvoiceController van de website.
// Template: facturen.html.

export const routes = {
    screens: ['facturen'],
    details: { factuur: /^\/facturen\/(\d+)$/ },
    parents: { factuur: 'facturen' },
    sections: { facturen: 'offertes', factuur: 'offertes' },
    paths: { '/facturen': 'facturen' },
};

// Alles wat een factuur toont of meetelt, moet na een wijziging vers worden opgehaald.
function invalidate(fetchedAt) {
    Object.keys(fetchedAt).filter((name) => /^(facturen|factuur|financieel|inkoop)/.test(name)).forEach((name) => { fetchedAt[name] = 0; });
    fetchedAt.inbox = fetchedAt.vandaag = 0;
}

export default function register({ Alpine, api, screen, fetchedAt, errorText }) {
    const os = () => Alpine.store('os');

    Alpine.data('invoices', () => ({
        ...screen('facturen', 'facturen'),
        status: '',
        page: 1,
        busy: null,
        view() {
            const query = new URLSearchParams({ ...(this.status ? { status: this.status } : {}), ...(this.page > 1 ? { page: this.page } : {}) }).toString();
            return this.show(query ? `facturen:${query}` : 'facturen', query ? `facturen?${query}` : 'facturen');
        },
        filter(value) {
            if (this.status === value) return;
            this.status = value;
            this.page = 1;
            this.view();
        },
        goPage(page) {
            this.page = page;
            this.view();
            window.scrollTo(0, 0);
        },
        async invoiceTerm(row) {
            if (this.busy) return;
            this.busy = row.term;
            try {
                const result = await api(`begrotingstermijnen/${row.term}/factureren`, { method: 'POST' });
                invalidate(fetchedAt);
                os().toast(result.message);
                os().go(result.path);
            } catch (error) {
                os().toast(errorText(error, 'Factureren lukte niet. Controleer de verbinding.'));
                this.load({ force: true });
            }
            this.busy = null;
        },
    }));

    Alpine.data('invoice', (id) => ({
        ...screen(`factuur:${id}`, `facturen/${id}`),
        busy: null,
        async setStatus(status) {
            if (this.busy) return;
            const questions = {
                verzonden: ['Factuur markeren als verstuurd? De factuurdatum wordt vandaag en de betaaltermijn van 14 dagen gaat lopen.', 'Verstuurd'],
                betaald: ['Betaling ontvangen? De factuur gaat op betaald voor het volledige bedrag.', 'Betaald'],
            };
            if (!(await os().ask(...questions[status]))) return;
            this.busy = status;
            try {
                const result = await api(`facturen/${id}`, { method: 'PATCH', body: { status } });
                invalidate(fetchedAt);
                await this.load({ force: true });
                os().toast(`Status: ${result.label}.`);
            } catch (error) {
                os().toast(errorText(error, 'Opslaan lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
        async remove() {
            if (this.busy || !(await os().ask(`Concept ${this.data.number} verwijderen? De termijn komt weer klaar te staan.`, 'Verwijderen'))) return;
            this.busy = 'delete';
            try {
                const result = await api(`facturen/${id}`, { method: 'DELETE' });
                invalidate(fetchedAt);
                os().toast(result.message);
                // Vervangen in plaats van erbij: terug mag niet op de verwijderde factuur uitkomen.
                location.replace('#/facturen');
            } catch (error) {
                os().toast(errorText(error, 'Verwijderen lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
    }));
}
