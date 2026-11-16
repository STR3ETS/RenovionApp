// Kwaliteit en oplevering (quality.index) en per project (quality.show): de acht stappen met hun harde
// voorwaarde, de controlepunten en de restpunten. Bekijken mag het hele team op eigen projecten; afvinken,
// doorzetten, tekenen en restpunten beheren alleen de projectleiding (via de QualityController van de
// website). Gegevens: api/app/kwaliteit (Api\App\QualityController). Template: kwaliteit.html.

export const routes = {
    screens: ['kwaliteit'],
    details: { oplevering: /^\/kwaliteit\/(\d+)$/ },
    parents: { kwaliteit: 'projecten', oplevering: 'projecten' },
    sections: { kwaliteit: 'projecten', oplevering: 'projecten' },
    paths: { '/kwaliteit': 'kwaliteit' },
};

export default function register({ Alpine, api, screen, fetchedAt, errorText }) {
    const os = () => Alpine.store('os');
    const emptySnag = () => ({ title: '', location: '', assigned_to: '', subcontractor_id: '', due_on: '', customer: false });

    Alpine.data('qualityList', () => ({
        ...screen('kwaliteit', 'kwaliteit'),
    }));

    Alpine.data('quality', (id) => ({
        ...screen(`oplevering:${id}`, `kwaliteit/${id}`),
        busy: null,
        signedName: '',
        snag: emptySnag(),

        async changed(result) {
            fetchedAt.kwaliteit = fetchedAt.inbox = fetchedAt[`project:${id}`] = fetchedAt.projecten = 0;
            await this.load({ force: true });
            if (result?.message) os().toast(result.message);
        },
        async run(key, path, options, fallback) {
            if (this.busy) return null;
            this.busy = key;
            let result = null;
            try {
                result = await api(path, options);
                await this.changed(result);
            } catch (error) {
                os().toast(errorText(error, fallback));
            }
            this.busy = null;
            return result;
        },

        advance() {
            if (this.data.blocker) {
                os().toast(this.data.blocker);
                return;
            }
            this.run('advance', `kwaliteit/${this.data.review_id}/volgende-stap`, { method: 'POST' }, 'Stap afronden lukte niet. Controleer de verbinding.');
        },
        toggleCheck(check) {
            this.run(`check:${check.id}`, `controlepunten/${check.id}/toggle`, { method: 'PATCH' }, 'Afvinken lukte niet. Controleer de verbinding.');
        },
        async sign() {
            const name = this.signedName.trim();
            if (!name) return;
            if (await this.run('sign', `kwaliteit/${this.data.review_id}/ondertekenen`, { method: 'POST', body: { signed_name: name } }, 'Vastleggen lukte niet. Controleer de verbinding.')) this.signedName = '';
        },
        // Opleverrapport opmaken (stap 7): bevriest scope, bewijs en restpunten; daarna het rapport openen.
        async makeReport() {
            const result = await this.run('report', `projecten/${id}/opleverrapport`, { method: 'POST' }, 'Rapport opmaken lukte niet. Controleer de verbinding.');
            if (result?.path) os().go(result.path);
        },
        setSnag(snag, status) {
            this.run(`snag:${snag.id}`, `restpunten/${snag.id}`, { method: 'PATCH', body: { status: status.value } }, 'Bijwerken lukte niet. Controleer de verbinding.');
        },
        async removeSnag(snag) {
            if (!(await os().ask(`Restpunt "${snag.title}" verwijderen?`, 'Verwijderen'))) return;
            this.run(`snag:${snag.id}`, `restpunten/${snag.id}`, { method: 'DELETE' }, 'Verwijderen lukte niet. Controleer de verbinding.');
        },
        async addSnag() {
            if (!this.snag.title.trim()) return;
            const { customer, ...fields } = this.snag;
            const body = Object.fromEntries(Object.entries({ ...fields, source: customer ? 'klant' : null }).filter(([, value]) => value !== '' && value !== null));
            if (await this.run('snag', `kwaliteit/${id}/restpunten`, { method: 'POST', body }, 'Vastleggen lukte niet. Controleer de verbinding.')) this.snag = emptySnag();
        },
    }));
}
