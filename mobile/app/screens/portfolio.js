// Portfolio (projects/portfolio): alle lopende projecten met hun vier stoplichten (planning, budget, kwaliteit,
// klant), voortgang tegen plan, volgende mijlpaal en grootste blokkade. Tellers per licht zijn het filter,
// daarnaast sorteren. Alleen kantoor. Gegevens: api/app/portfolio (Api\App\PortfolioController, filteren en
// sorteren gedeeld met de website). Template: portfolio.html.

export const routes = {
    screens: ['portfolio'],
    parents: { portfolio: 'projecten' },
    sections: { portfolio: 'projecten' },
    paths: { '/portfolio': 'portfolio' },
};

export default function register({ Alpine, screen }) {
    Alpine.data('portfolio', () => ({
        ...screen('portfolio', 'portfolio'),
        light: '',
        sort: 'gezondheid',
        dot: (color) => ({ green: 'bg-green-500', amber: 'bg-amber-500', red: 'bg-red-500' }[color] ?? 'bg-gray-400'),
        bar: (color) => ({ green: 'bg-green-500', amber: 'bg-amber-500', red: 'bg-red-500' }[color] ?? 'bg-brand-500'),
        card: (color) => ({ green: 'border-green-200 bg-green-50', amber: 'border-amber-200 bg-amber-50', red: 'border-red-200 bg-red-50' }[color] ?? 'border-gray-200 bg-white'),
        border: (color) => ({ green: 'border-l-green-500', amber: 'border-l-amber-500', red: 'border-l-red-500' }[color] ?? 'border-l-gray-300'),
        view() {
            const query = new URLSearchParams({ ...(this.light ? { licht: this.light } : {}), ...(this.sort !== 'gezondheid' ? { sorteer: this.sort } : {}) }).toString();
            return this.show(query ? `portfolio:${query}` : 'portfolio', query ? `portfolio?${query}` : 'portfolio');
        },
        pickLight(value) {
            this.light = this.light === value ? '' : value;
            this.view();
        },
        pickSort(value) {
            if (this.sort === value) return;
            this.sort = value;
            this.view();
        },
    }));
}
