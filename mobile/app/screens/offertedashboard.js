// Offertedashboard: wat is verstuurd, geopend en getekend, en wat kan er nog komen (quotes/dashboard).
// Periode en verkoper kiezen haalt die weergave op (eerst wat ervan bewaard is). Een offerte opent #/offerte/{id}.
// Gegevens: api/app/offertedashboard (Api\App\QuoteDashboardController). Template: offertedashboard.html.

export const routes = {
    screens: ['offertedashboard'],
    details: {},
    parents: { offertedashboard: 'offertes' },
    sections: { offertedashboard: 'offertes' },
    paths: { '/offertedashboard': 'offertedashboard' },
};

export default function register({ Alpine, screen }) {
    Alpine.data('quoteDashboard', () => ({
        ...screen('offertedashboard', 'offertedashboard'),
        period: 'jaar',
        seller: '',
        // De keuzelijsten blijven staan terwijl een andere weergave laadt.
        periods: null,
        sellers: null,
        filter() {
            const query = new URLSearchParams({ periode: this.period, ...(this.seller ? { verkoper: this.seller } : {}) });
            this.show(`offertedashboard:${this.period}:${this.seller}`, `offertedashboard?${query}`);
        },
    }));
}
