// Rapportages (reports/index) en Marketing (reports/marketing): een rapport is een momentopname uit de data
// die er is (week, project, sales, financieel, kwaliteit), met afdrukken via de printdialoog van de telefoon;
// marketing per periode: kerncijfers, beste bron, aanvragen per maand als balken en de verdeling per bron,
// kanaal en dienst. Gegevens: api/app/rapportages en api/app/marketing (Api\App\ReportController).
// Template: rapportages.html.

export const routes = {
    screens: ['rapportages', 'marketing'],
    parents: { marketing: 'rapportages' },
    sections: { rapportages: 'offertes', marketing: 'offertes' },
    paths: { '/rapportages': 'rapportages', '/marketing': 'marketing' },
    // /rapportages?type=project&project=5 en /marketing?periode=kwartaal openen het scherm met die keuze.
    query: ['rapportages', 'marketing'],
};

const view = (name, params) => {
    const query = new URLSearchParams(Object.entries(params).filter(([, value]) => value)).toString();
    return query ? [`${name}:${query}`, `${name}?${query}`] : [name, name];
};

export default function register({ Alpine, screen }) {
    const os = () => Alpine.store('os');

    Alpine.data('reports', () => {
        const base = screen('rapportages', 'rapportages');

        return {
            ...base,
            type: 'week',
            project: '',
            init() {
                const { type = 'week', project = '' } = os().query ?? {};
                this.type = type;
                this.project = project;
                [this.cacheName, this.path] = this.target();
                return base.init.call(this);
            },
            target() {
                return view('rapportages', { type: this.type === 'week' ? '' : this.type, project: this.type === 'project' ? this.project : '' });
            },
            choose(type) {
                if (this.type === type) return;
                this.type = type;
                this.show(...this.target());
            },
            pickProject() {
                this.show(...this.target());
            },
            print() {
                window.print();
            },
        };
    });

    Alpine.data('marketing', () => {
        const base = screen('marketing', 'marketing');

        return {
            ...base,
            period: 'jaar',
            init() {
                this.period = os().query?.periode ?? 'jaar';
                [this.cacheName, this.path] = this.target();
                return base.init.call(this);
            },
            target() {
                return view('marketing', { periode: this.period === 'jaar' ? '' : this.period });
            },
            choose() {
                this.show(...this.target());
            },
            get maxMonth() {
                return Math.max(1, ...(this.data?.per_month ?? []).map((month) => month.count));
            },
            bar(count) {
                return `height: ${Math.max(2, Math.round((count / this.maxMonth) * 100))}%`;
            },
        };
    });
}
