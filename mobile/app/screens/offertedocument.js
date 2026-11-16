// Offertedocument: de offerte zoals de klant hem ziet, in de app zelf, met PDF via de printdialoog van de
// telefoon ("Opslaan als PDF"). De blokken komen als HTML uit dezelfde Blade-weergaven als de klantpagina,
// zodat app en klantpagina gelijk blijven. Gegevens: api/app/offertes/{id}/document. Template: offertedocument.html.

export const routes = {
    screens: [],
    // /offertes/5/document (links.document van de offerte) opent dit scherm.
    details: { offertedocument: /^\/offertes\/(\d+)\/document$/ },
    parents: { offertedocument: 'offertes' },
    sections: { offertedocument: 'offertes' },
    paths: {},
    // Altijd licht, ook in de donkere weergave: zo ziet de klant het, en zo komt het op papier.
    light: ['offertedocument'],
};

export default function register({ Alpine, screen, printPart }) {
    Alpine.data('quoteDocument', (id) => ({
        ...screen(`offertedocument:${id}`, `offertes/${id}/document`),
        printing: false,
        async pdf() {
            if (!this.data || this.printing) return;
            this.printing = true;
            try {
                await printPart(this.$refs.doc, `Offerte ${this.data.number}`);
            } catch {
                Alpine.store('os').toast('Printen lukte niet op dit toestel.');
            }
            this.printing = false;
        },
    }));
}
