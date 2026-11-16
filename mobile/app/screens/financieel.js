// Financieel (finance/index) en Nacalculatie (finance/cost-reviews): kerncijfers, omzet per maand en
// cashflow als balken, debiteuren en crediteuren; per project begroot tegenover werkelijk en verwacht.
// Gegevens: api/app/financieel en api/app/nacalculatie (Api\App\FinanceController). Template: financieel.html.

export const routes = {
    screens: ['financieel', 'nacalculatie'],
    sections: { financieel: 'offertes', nacalculatie: 'offertes' },
    paths: { '/financieel': 'financieel', '/nacalculatie': 'nacalculatie' },
};

// Hoogte van een balk in procenten van de hoogste, met een minimum zodat een lege maand zichtbaar blijft.
const bar = (value, max) => `height: ${Math.max(2, Math.round((value / (max || 1)) * 100))}%`;
const thousands = (value) => `${Math.round(value / 1000).toLocaleString('nl-NL')}k`;

export default function register({ Alpine, screen }) {
    Alpine.data('finance', () => ({
        ...screen('financieel', 'financieel'),
        bar,
        thousands,
        get maxRevenue() {
            return Math.max(1, ...(this.data?.revenue ?? []).map((month) => month.amount));
        },
        get maxCash() {
            return Math.max(1, ...(this.data?.cashflow ?? []).flatMap((month) => [month.in, month.out]));
        },
    }));

    Alpine.data('costReviews', () => ({
        ...screen('nacalculatie', 'nacalculatie'),
        open: null,
        toggle(id) {
            this.open = this.open === id ? null : id;
        },
    }));
}
