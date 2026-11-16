// Dagbriefing van Nova (/nova/briefing): wat er vandaag speelt en wat als eerste aandacht vraagt, met
// vernieuwen (?refresh=1, de server bewaart hem anders 3 uur) en doorvragen aan Nova. Alleen kantoor.
// Gegevens: api/app/nova/briefing (NovaController@briefing, dezelfde als de Nova-sheet). Template: briefing.html.

export const routes = {
    screens: ['briefing'],
    parents: { briefing: 'vandaag' },
    paths: { '/nova/briefing': 'briefing' },
};

export default function register({ Alpine, screen, fetchScreen, errorText }) {
    Alpine.data('briefing', () => ({
        ...screen('briefing', 'nova/briefing'),
        refreshing: false,
        // Een nieuwe briefing laten schrijven; hij komt in dezelfde bewaarde gegevens als de gewone.
        async refresh() {
            if (this.refreshing) return;
            this.refreshing = true;
            try {
                this.present(await fetchScreen('briefing', 'nova/briefing?refresh=1', { force: true }));
                this.failed = false;
            } catch (error) {
                Alpine.store('os').toast(errorText(error, 'Vernieuwen lukte niet. Controleer de verbinding.'));
            }
            this.refreshing = false;
        },
    }));
}
