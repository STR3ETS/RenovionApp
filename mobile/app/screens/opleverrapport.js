// Opleverrapport (delivery-reports.show): het rapport uit de bevroren snapshot, met planning, fasen,
// uitgevoerd werk met foto-bewijs, meerwerk, restpunten, garantie en de handtekeningen. De projectleiding
// kan hergenereren en ondertekenen namens Renovion (via de DeliveryReportController van de website).
// PDF: de serverpagina met de printdialoog. Gegevens: api/app/opleverrapporten/{id}. Template: opleverrapport.html.

const API = import.meta.env.VITE_RENOVION_API;

export const routes = {
    screens: [],
    details: { opleverrapport: /^\/opleverrapporten\/(\d+)$/ },
    parents: { opleverrapport: 'projecten' },
    sections: { opleverrapport: 'projecten' },
    paths: {},
};

export default function register({ Alpine, api, screen, fetchedAt, errorText }) {
    const os = () => Alpine.store('os');

    Alpine.data('deliveryReport', (id) => ({
        ...screen(`opleverrapport:${id}`, `opleverrapporten/${id}`),
        busy: null,
        viewer: null,
        photoUrl: (photo) => `${API}${photo.url}`,
        async regenerate() {
            if (this.busy || !(await os().ask('Rapport opnieuw opbouwen met de actuele projectdata?', 'Hergenereren'))) return;
            this.busy = 'regenerate';
            try {
                const result = await api(`projecten/${this.data.project.id}/opleverrapport`, { method: 'POST' });
                fetchedAt[`project:${this.data.project.id}`] = 0;
                await this.load({ force: true });
                os().toast(result.message);
            } catch (error) {
                os().toast(errorText(error, 'Hergenereren lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
        async sign() {
            if (this.busy || !(await os().ask('Rapport ondertekenen namens Renovion?', 'Ondertekenen'))) return;
            this.busy = 'sign';
            try {
                const result = await api(`opleverrapporten/${id}/ondertekenen`, { method: 'POST' });
                fetchedAt[`project:${this.data.project.id}`] = 0;
                await this.load({ force: true });
                os().toast(result.message);
            } catch (error) {
                os().toast(errorText(error, 'Ondertekenen lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
    }));
}
