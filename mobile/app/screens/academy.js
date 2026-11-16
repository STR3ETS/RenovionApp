// Academy (academy/index): jouw leerpad, de rondleidingen per onderwerp, de afspraken en de veelgestelde
// vragen. Een rondleiding (academy/show) opent als #/rondleiding?tour=basis&stap=2: de id is een woord, geen
// getal, dus via een query in plaats van een detailscherm. Een stap openen telt als voortgang (zoals op de
// website), afronden gaat via AcademyController@complete. Gegevens: api/app/academy (Api\App\AcademyController).
// Template: academy.html.

export const routes = {
    screens: ['academy', 'rondleiding'],
    query: ['rondleiding'],
    // /academy/basis?stap=2 (links uit de API en van de server) => de rondleiding in de app.
    rewrites: [
        (pathname, params) => {
            const match = pathname.match(/^\/academy\/([a-z0-9-]+)$/);
            if (!match) return null;
            const step = parseInt(params.get('stap') ?? '', 10);
            return `#/rondleiding?tour=${match[1]}${step > 0 ? `&stap=${step}` : ''}`;
        },
    ],
    parents: { rondleiding: 'academy' },
    sections: { academy: 'organisatie', rondleiding: 'organisatie' },
    paths: { '/academy': 'academy' },
};

export default function register({ Alpine, api, screen, fetchedAt, cache, errorText }) {
    const os = () => Alpine.store('os');

    Alpine.data('academy', () => ({
        ...screen('academy', 'academy'),
        faqOpen: null,
    }));

    Alpine.data('academyTour', (id, start) => {
        const base = screen(`rondleiding:${id}:${start}`, `academy/${id}?stap=${start}`);

        return {
            ...base,
            step: start,
            busy: false,
            async init() {
                await base.init.call(this);
                fetchedAt.academy = 0; // geopend = begonnen: het overzicht laat dat zien
            },
            // Naar een andere stap: de vorige blijft staan tot de nieuwe er is (geen grijze blokken tussendoor).
            async goto(step) {
                if (step < 0 || (this.data && step >= this.data.count) || step === (this.data?.step ?? this.step)) return;
                this.step = step;
                this.cacheName = `rondleiding:${id}:${step}`;
                this.path = `academy/${id}?stap=${step}`;
                this.present(cache.read(this.cacheName));
                history.replaceState(null, '', `#/rondleiding?tour=${id}${step > 0 ? `&stap=${step}` : ''}`);
                window.scrollTo(0, 0);
                await this.load({ force: true });
                fetchedAt.academy = 0;
                if (this.data?.step !== step) os().toast('Geen verbinding. Probeer het zo nog eens.');
            },
            async finish() {
                if (this.busy) return;
                this.busy = true;
                try {
                    const { message } = await api(`academy/${id}/afronden`, { method: 'POST' });
                    Object.keys(fetchedAt).filter((name) => name === 'academy' || name.startsWith(`rondleiding:${id}:`)).forEach((name) => { fetchedAt[name] = 0; });
                    os().toast(message);
                    location.hash = '#/academy';
                } catch (error) {
                    os().toast(errorText(error, 'Afronden lukte niet. Controleer de verbinding.'));
                }
                this.busy = false;
            },
        };
    });
}
