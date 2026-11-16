// Calculaties: de lijst per 25 (calculations/index) en de calculatie zelf (calculations/show): regels met aantal
// aanpassen en verwijderen, regel toevoegen uit de prijsbibliotheek, opslagen en btw, definitief maken of
// heropenen, verwijderen, en "offerte maken" (opent daarna de offerte-builder als serverpagina, zoals de website).
// Gegevens: api/app/calculaties[/{id}] (Api\App\CalculationController); wijzigen via de controllers van de website.
// Template: calculaties.html.

export const routes = {
    screens: ['calculaties'],
    details: { calculatie: /^\/calculaties\/(\d+)$/ },
    parents: { calculatie: 'calculaties' },
    sections: { calculaties: 'offertes', calculatie: 'offertes' },
    paths: { '/calculaties': 'calculaties' },
};

const EMPTY_LINE = () => ({ price_item_id: '', type: 'arbeid', description: '', quantity: 1, unit: '', unit_price: '', surcharge_pct: 0 });

export default function register({ Alpine, api, screen, cache, fetchedAt, errorText }) {
    Alpine.data('calculations', () => ({
        ...screen('calculaties', 'calculaties'),
        extra: [],
        nextPage: null,
        loadingMore: false,
        get list() {
            const first = this.data?.calculations ?? [];
            const ids = new Set(first.map((item) => item.id));
            return [...first, ...this.extra.filter((item) => !ids.has(item.id))];
        },
        get hasMore() {
            return this.nextPage !== null ? this.nextPage > 0 : Boolean(this.data?.more);
        },
        async more() {
            if (this.loadingMore || !this.data) return;
            this.loadingMore = true;
            const page = this.nextPage || (this.data.page + 1);
            try {
                const result = await api(`calculaties?pagina=${page}`);
                this.extra.push(...result.calculations);
                this.nextPage = result.more ? page + 1 : 0;
            } catch (error) {
                Alpine.store('os').toast(errorText(error, 'Meer laden lukte niet. Controleer de verbinding.'));
            }
            this.loadingMore = false;
        },
    }));

    Alpine.data('calculation', (id) => ({
        ...screen(`calculatie:${id}`, `calculaties/${id}`),
        busy: null,
        settings: null, // opslagen en btw zoals ingetypt (null = die van de server)
        // Regel toevoegen, met de zoeker in de prijsbibliotheek.
        q: '',
        results: [],
        searching: false,
        line: EMPTY_LINE(),
        searchTimer: null,
        destroy() {
            clearTimeout(this.searchTimer);
        },
        get form() {
            return this.settings ?? this.data?.settings ?? { risk_pct: 0, margin_pct: 0, vat_pct: 0 };
        },
        // Na een wijziging: deze calculatie vers (of het antwoord meteen tonen), en de lijst bij openen opnieuw.
        async changed(fresh = null) {
            fetchedAt.calculaties = 0;
            if (fresh) {
                cache.write(this.cacheName, fresh, JSON.stringify(fresh));
                this.present(cache.read(this.cacheName));
            } else {
                await this.load({ force: true });
            }
        },
        async run(key, request, fallback) {
            if (this.busy) return null;
            this.busy = key;
            try {
                return await request();
            } catch (error) {
                Alpine.store('os').toast(errorText(error, fallback));
                return null;
            } finally {
                this.busy = null;
            }
        },
        search() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(async () => {
                const q = this.q.trim();
                if (q.length < 2) {
                    this.results = [];
                    return;
                }
                this.searching = true;
                try {
                    this.results = (await api(`prijsitems?q=${encodeURIComponent(q)}`)).items;
                } catch {
                    this.results = [];
                }
                this.searching = false;
            }, 300);
        },
        pick(item) {
            this.line = { price_item_id: item.id, type: item.type, description: item.name, quantity: this.line.quantity || 1, unit: item.unit, unit_price: item.price, surcharge_pct: item.surcharge_pct };
            this.q = `${item.name} - ${item.source}`;
            this.results = [];
        },
        async addLine() {
            const line = this.line;
            const body = { ...line, price_item_id: line.price_item_id || null, unit: line.unit || null };
            const done = await this.run('line', () => api(`calculaties/${id}/regels`, { method: 'POST', body }), 'Regel toevoegen lukte niet. Controleer de verbinding.');
            if (!done) return;
            this.line = EMPTY_LINE();
            this.q = '';
            Alpine.store('os').toast(done.message);
            await this.changed();
        },
        async setQuantity(row, value) {
            if (value === '' || Number(value) === row.quantity) return;
            const done = await this.run(`qty:${row.id}`, () => api(`calculaties/${id}/regels/${row.id}`, { method: 'PATCH', body: { quantity: value } }), 'Aantal opslaan lukte niet. Controleer de verbinding.');
            await this.changed();
            if (done) Alpine.store('os').toast(done.message);
        },
        async removeLine(row) {
            if (!(await Alpine.store('os').ask('Regel verwijderen?', 'Verwijderen'))) return;
            const done = await this.run(`del:${row.id}`, () => api(`calculaties/${id}/regels/${row.id}`, { method: 'DELETE' }), 'Verwijderen lukte niet. Controleer de verbinding.');
            if (!done) return;
            Alpine.store('os').toast(done.message);
            await this.changed();
        },
        async saveSettings() {
            const fresh = await this.run('settings', () => api(`calculaties/${id}`, { method: 'PATCH', body: this.form }), 'Opslaan lukte niet. Controleer de verbinding.');
            if (!fresh) return;
            this.settings = null;
            await this.changed(fresh);
            Alpine.store('os').toast('Calculatie bijgewerkt.');
        },
        async setStatus(status) {
            if (status === 'definitief' && !(await Alpine.store('os').ask('Calculatie definitief maken? De regels en prijzen liggen daarna vast.', 'Definitief maken'))) return;
            const fresh = await this.run('status', () => api(`calculaties/${id}`, { method: 'PATCH', body: { status } }), 'Status wijzigen lukte niet. Controleer de verbinding.');
            if (!fresh) return;
            await this.changed(fresh);
            Alpine.store('os').toast(status === 'definitief' ? 'Calculatie staat op definitief.' : 'Calculatie heropend.');
        },
        async remove() {
            if (!(await Alpine.store('os').ask('Calculatie verwijderen? Dit kan niet ongedaan worden gemaakt.', 'Verwijderen'))) return;
            const done = await this.run('delete', () => api(`calculaties/${id}`, { method: 'DELETE' }), 'Verwijderen lukte niet. Controleer de verbinding.');
            if (!done) return;
            fetchedAt.calculaties = 0;
            Alpine.store('os').toast(done.message);
            Alpine.store('os').go(done.path);
        },
        async makeQuote() {
            const quote = await this.run('quote', () => api(`calculaties/${id}/offerte`, { method: 'POST' }), 'Offerte maken lukte niet. Controleer de verbinding.');
            if (!quote) return;
            fetchedAt.offertes = fetchedAt.aanvragen = fetchedAt.vandaag = 0;
            Alpine.store('os').toast(`Offerte ${quote.number} gemaakt.`);
            // Zoals de website: direct de builder in om de teksten te controleren.
            Alpine.store('os').openWeb(quote.builder);
        },
    }));
}
