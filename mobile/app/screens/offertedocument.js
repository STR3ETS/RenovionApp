// Offertedocument: de offerte zoals de klant hem ziet, in de app zelf, met PDF via de printdialoog van de
// telefoon ("Opslaan als PDF"). De blokken komen als HTML uit dezelfde Blade-weergaven als de klantpagina,
// zodat app en klantpagina gelijk blijven. Gegevens: api/app/offertes/{id}/document. Template: offertedocument.html.
//
// Het scherm volgt de weergave van de app (licht of donker); het document zelf ligt erop als wit vel papier
// (.os-paper) en staat in een shadow root: daar komt de donkere weergave van de app niet bij, dus het ziet er
// altijd uit zoals bij de klant. Op papier en in de PDF is het ook altijd licht (printPart).

export const routes = {
    screens: [],
    // /offertes/5/document (links.document van de offerte) opent dit scherm.
    details: { offertedocument: /^\/offertes\/(\d+)\/document$/ },
    parents: { offertedocument: 'offertes' },
    sections: { offertedocument: 'offertes' },
    paths: {},
};

const escape = (text) => String(text ?? '').replace(/[&<>"']/g, (char) => `&#${char.charCodeAt(0)};`);

// Dezelfde balk als op de klantpagina (quotes/public): ondertekend, afgewezen of verlopen.
const BANNERS = {
    ok: 'bg-green-700 text-white',
    off: 'bg-gray-200 text-gray-600',
    late: 'bg-amber-100 text-amber-800',
};

// Het document als HTML: de balk, de blokken en de voet (alles uit de API, de tekst van de balk ontsnapt).
function documentHtml(data) {
    const banner = data.banner
        ? `<div class="rounded-2xl px-6 py-3 text-sm font-semibold ${BANNERS[data.banner.tone] ?? ''}">${escape(data.banner.text)}</div>`
        : '';
    return `<div class="space-y-4">${banner}${data.html}</div>`;
}

export default function register({ Alpine, screen, printPart }) {
    Alpine.data('quoteDocument', (id) => ({
        ...screen(`offertedocument:${id}`, `offertes/${id}/document`),
        printing: false,
        // Het vel: een shadow root met de stijl van de app, zodat alleen de lichte kleuren gelden.
        paint() {
            const host = this.$refs.paper;
            if (!host || !this.data) return;
            const root = host.shadowRoot ?? host.attachShadow({ mode: 'open' });
            const styles = [...document.querySelectorAll('link[rel="stylesheet"], style')].map((node) => node.outerHTML).join('');
            root.innerHTML = `${styles}<div class="os-sheet font-sans antialiased">${documentHtml(this.data)}</div>`;
        },
        async pdf() {
            if (!this.data || this.printing) return;
            this.printing = true;
            try {
                const sheet = document.createElement('div');
                sheet.className = 'os-sheet font-sans antialiased';
                sheet.innerHTML = documentHtml(this.data);
                await printPart(sheet, `Offerte ${this.data.number}`);
            } catch {
                Alpine.store('os').toast('Printen lukte niet op dit toestel.');
            }
            this.printing = false;
        },
    }));
}
