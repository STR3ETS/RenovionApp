// Aan de slag (onboarding/show): hoe het systeem werkt (de keten, of voor uitvoerders hun eigen stappen), voor
// kantoor de live checklist en wat Nova doet, waar je wat vindt en "Goed om te weten". Uit het menu halen en
// weer tonen via de OnboardingController van de website. Gegevens: api/app/aan-de-slag
// (Api\App\OnboardingController). Template: aandeslag.html.

export const routes = {
    screens: ['aandeslag'],
    sections: { aandeslag: 'organisatie' },
    paths: { '/aan-de-slag': 'aandeslag' },
};

export default function register({ Alpine, api, screen, fetchedAt, errorText }) {
    const os = () => Alpine.store('os');

    Alpine.data('onboarding', () => ({
        ...screen('aandeslag', 'aan-de-slag'),
        busy: false,
        // Verbergen gaat (net als op de website) daarna naar Vandaag; weer tonen blijft hier.
        async toggleMenu() {
            if (this.busy || !this.data) return;
            const hide = !this.data.dismissed;
            this.busy = true;
            try {
                const { message } = await api(hide ? 'aan-de-slag/verbergen' : 'aan-de-slag/tonen', { method: 'POST' });
                fetchedAt.aandeslag = fetchedAt.vandaag = 0;
                os().toast(message);
                if (hide) location.hash = '#/vandaag';
                else await this.load({ force: true });
            } catch (error) {
                os().toast(errorText(error, 'Opslaan lukte niet. Controleer de verbinding.'));
            }
            this.busy = false;
        },
        toChecklist() {
            this.$refs.checklist?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },
    }));
}
