// Eigen mensen (workforce/index) en Team (team/index). Mensen: per persoon certificaten, bezetting in de week,
// de planning van die week en afwezigheid, met certificaat en afwezigheid toevoegen of weghalen en (alleen
// directie) functie, uurtarief, uren per week en planbaar. Team: de teamleden en de rechtenmatrix per rol;
// aanmaken en bewerken zijn eigen formulieren (/team/aanmaken, /team/{id}/bewerken).
// Gegevens: api/app/mensen (Api\App\WorkforceController) en api/app/team (Api\App\TeamOverviewController);
// wijzigen via de WorkforceController van de website. Template: mensen.html.

export const routes = {
    screens: ['mensen', 'team'],
    parents: { mensen: 'team' },
    sections: { mensen: 'mensen', team: 'mensen' },
    paths: { '/mensen': 'mensen', '/team': 'team' },
};

export default function register({ Alpine, api, screen, fetchedAt, errorText }) {
    const os = () => Alpine.store('os');

    Alpine.data('workforce', () => ({
        ...screen('mensen', 'mensen'),
        open: null,
        busy: null,
        forms: {},
        setWeek(date) {
            this.open = null;
            this.show(date ? `mensen:${date}` : 'mensen', date ? `mensen?week=${date}` : 'mensen');
        },
        toggle(person) {
            this.open = this.open === person.id ? null : person.id;
            if (this.open && !this.forms[person.id]) this.resetForms(person);
        },
        resetForms(person) {
            this.forms[person.id] = {
                cert: { type: this.data.certificate_types[0]?.value ?? '', issued_on: '' },
                absence: { type: this.data.absence_types[0]?.value ?? 'vakantie', from: '', until: '' },
                details: { ...person.form, hourly_rate: person.form.hourly_rate ?? '', weekly_capacity: person.form.weekly_capacity ?? '' },
            };
        },
        async run(key, request, fallback) {
            if (this.busy) return false;
            this.busy = key;
            try {
                const result = await request();
                Object.keys(fetchedAt).filter((name) => name.startsWith('mensen')).forEach((name) => { fetchedAt[name] = 0; });
                fetchedAt.planning = 0;
                await this.load({ force: true });
                os().toast(result.message);
                return true;
            } catch (error) {
                os().toast(errorText(error, fallback));
                return false;
            } finally {
                this.busy = null;
            }
        },
        async addCertificate(person) {
            const form = this.forms[person.id].cert;
            if (await this.run(`cert:${person.id}`, () => api(`mensen/${person.id}/certificaten`, { method: 'POST', body: form }), 'Toevoegen lukte niet. Controleer de verbinding.')) {
                form.issued_on = '';
            }
        },
        async removeCertificate(cert) {
            if (!(await os().ask(`Certificaat ${cert.type} verwijderen?`, 'Verwijderen'))) return;
            await this.run(`cert-del:${cert.id}`, () => api(`certificaten/${cert.id}`, { method: 'DELETE' }), 'Verwijderen lukte niet. Controleer de verbinding.');
        },
        async addAbsence(person) {
            const form = this.forms[person.id].absence;
            if (await this.run(`absence:${person.id}`, () => api(`mensen/${person.id}/afwezigheid`, { method: 'POST', body: form }), 'Vastleggen lukte niet. Controleer de verbinding.')) {
                form.from = form.until = '';
            }
        },
        async removeAbsence(absence) {
            if (!(await os().ask(`${absence.label} verwijderen?`, 'Verwijderen'))) return;
            await this.run(`absence-del:${absence.id}`, () => api(`afwezigheid/${absence.id}`, { method: 'DELETE' }), 'Verwijderen lukte niet. Controleer de verbinding.');
        },
        async saveDetails(person) {
            const form = this.forms[person.id].details;
            // Uren per week mag niet leeg (de kolom heeft een standaard van 40): leeg = niet meesturen.
            const { weekly_capacity: capacity, ...rest } = form;
            const body = { ...rest, hourly_rate: form.hourly_rate === '' ? null : form.hourly_rate, ...(capacity === '' || capacity === null ? {} : { weekly_capacity: capacity }) };
            await this.run(`details:${person.id}`, () => api(`mensen/${person.id}`, { method: 'PATCH', body }), 'Opslaan lukte niet. Controleer de verbinding.');
        },
    }));

    Alpine.data('teamOverview', () => ({
        ...screen('team', 'team'),
        matrix: false,
        role: null,
        get currentRole() {
            return this.role ?? this.data?.matrix.roles[0]?.value ?? null;
        },
    }));
}
