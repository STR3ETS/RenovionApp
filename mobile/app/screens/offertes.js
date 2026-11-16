// Offertes: alle offertes per status, per 25 met "Meer laden", zoals de website (quotes/index).
// Een offerte opent het offertescherm van de app (#/offerte/{id}, screens/offerte.*).
// Gegevens: api/app/offertes (Api\App\QuoteListController). Template: offertes.html.

export const routes = {
    screens: ['offertes'],
    details: {},
    parents: {},
    sections: { offertes: 'offertes' },
    paths: { '/offertes': 'offertes' },
};

export default function register({ Alpine, api, screen, errorText }) {
    Alpine.data('quotes', () => ({
        ...screen('offertes', 'offertes'),
        status: '',
        statuses: null, // de chips blijven staan als een filter laadt (x-effect in de template)
        // Volgende pagina's: niet bewaard, alleen zolang je op dit scherm bent.
        extra: [],
        nextPage: null,
        loadingMore: false,
        get list() {
            const first = this.data?.quotes ?? [];
            const ids = new Set(first.map((quote) => quote.id));
            return [...first, ...this.extra.filter((quote) => !ids.has(quote.id))];
        },
        get hasMore() {
            return this.nextPage !== null ? this.nextPage > 0 : Boolean(this.data?.more);
        },
        setStatus(value) {
            if (value === this.status) return;
            this.status = value;
            this.extra = [];
            this.nextPage = null;
            this.show(value ? `offertes:${value}` : 'offertes', value ? `offertes?status=${value}` : 'offertes');
        },
        async more() {
            if (this.loadingMore || !this.data) return;
            this.loadingMore = true;
            const page = this.nextPage || (this.data.page + 1);
            try {
                const query = new URLSearchParams({ pagina: page, ...(this.status ? { status: this.status } : {}) });
                const result = await api(`offertes?${query}`);
                const known = new Set(this.list.map((quote) => quote.id));
                this.extra.push(...result.quotes.filter((quote) => !known.has(quote.id)));
                this.nextPage = result.more ? page + 1 : 0;
            } catch (error) {
                Alpine.store('os').toast(errorText(error, 'Meer laden lukte niet. Controleer de verbinding.'));
            }
            this.loadingMore = false;
        },
    }));
}
