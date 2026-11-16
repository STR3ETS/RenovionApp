// Offerte: signalen, klantlink, posten, versies en de status (versturen, opvolgen, akkoord, afgewezen).
// Het document zelf opent als lichte serverpagina (quotes.document), met PDF via de printdialoog.
// Gegevens: api/app/offertes/{id} (Api\App\QuoteController). Template: offerte.html.

export const routes = {
    // Schermen zonder id (#/naam). Een detailscherm (#/naam/5) staat in details.
    screens: [],
    // Pad op de server => scherm in de app (go() en links uit de API).
    details: { offerte: /^\/offertes\/(\d+)$/ },
    // Welk menu-item/tab oplicht op dit scherm.
    parents: { offerte: 'offertes' },
    // Sectiekleur (native-app.css, data-sec).
    sections: { offerte: 'offertes' },
    // Serverpaden zonder id die dit scherm openen ('/offertes': 'offertes').
    paths: {},
};

export default function register({ Alpine, api, screen, fetchedAt, errorText }) {
    Alpine.data('quote', (id) => ({
        ...screen(`offerte:${id}`, `offertes/${id}`),
        busy: null,
        copied: false,
        versionNote: '',
        async changed() {
            fetchedAt.aanvragen = fetchedAt.inbox = fetchedAt.vandaag = 0;
            await this.load({ force: true });
        },
        async setStatus(action) {
            if (this.busy) return;
            const questions = {
                verstuurd: ['Offerte versturen? Deze versie wordt bevroren en de klantlink gaat aan.', 'Versturen'],
                akkoord: ['Offerte akkoord? Er wordt meteen een project aangemaakt.', 'Akkoord'],
                afgewezen: ['Offerte markeren als afgewezen?', 'Afgewezen'],
            };
            if (questions[action.value] && !(await Alpine.store('os').ask(...questions[action.value]))) return;
            this.busy = action.value;
            try {
                const result = await api(`offertes/${id}/status`, { method: 'PATCH', body: { status: action.value } });
                fetchedAt.projecten = 0;
                await this.changed();
                Alpine.store('os').toast(`Status: ${result.label}.`);
                if (result.project) Alpine.store('os').go(result.project);
            } catch (error) {
                Alpine.store('os').toast(errorText(error, 'Status wijzigen lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
        async newVersion() {
            const note = this.versionNote.trim();
            if (!note || this.busy) return;
            this.busy = 'version';
            try {
                const { version } = await api(`offertes/${id}/nieuwe-versie`, { method: 'POST', body: { note } });
                this.versionNote = '';
                await this.changed();
                Alpine.store('os').toast(`Versie v${version} gestart: de offerte staat weer op concept.`);
            } catch (error) {
                Alpine.store('os').toast(errorText(error, 'Nieuwe versie starten lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
        async copyLink() {
            try {
                await navigator.clipboard.writeText(this.data.public_url);
                this.copied = true;
                setTimeout(() => { this.copied = false; }, 2000);
            } catch {
                Alpine.store('os').toast('Kopiëren lukte niet.');
            }
        },
    }));
}
