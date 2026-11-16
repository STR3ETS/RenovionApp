// Uitvoering van een project (tab "uitvoering" van het projectdossier): alle fasen met hun gate en de werkpakketten.
// Kantoor wijzigt de fasestatus, geeft een fase vrij en voegt werkpakketten toe. "Naar uitvoering" in het
// projectdossier gaat naar /projecten/{id}?tab=uitvoering; de rewrite hieronder opent dan dit scherm.
// Gegevens: api/app/projecten/{id}/uitvoering (Api\App\ProjectExecutionController). Template: uitvoering.html.

export const routes = {
    screens: [],
    details: { uitvoering: /^\/projecten\/(\d+)\/uitvoering$/ },
    parents: { uitvoering: 'projecten' },
    sections: { uitvoering: 'projecten' },
    paths: {},
    rewrites: [
        (pathname, params) => {
            const match = pathname.match(/^\/projecten\/(\d+)$/);
            return match && params.get('tab') === 'uitvoering' ? `#/uitvoering/${match[1]}` : null;
        },
    ],
};

export default function register({ Alpine, api, screen, fetchedAt, errorText }) {
    Alpine.data('execution', (id) => ({
        ...screen(`uitvoering:${id}`, `projecten/${id}/uitvoering`),
        busy: null,
        // Open formulier per fase: vrijgeven (met notitie) of een werkpakket toevoegen.
        approving: null,
        note: '',
        adding: null,
        pkg: { name: '', responsible_id: '', deadline: '' },
        errors: {},
        // Na een wijziging: dit scherm vers, en het projectdossier en de lijst bij openen opnieuw.
        async changed() {
            fetchedAt[`project:${id}`] = fetchedAt.projecten = fetchedAt.vandaag = 0;
            await this.load({ force: true });
        },
        async setStatus(phase, value) {
            if (value === phase.status.value || this.busy) return;
            this.busy = `status:${phase.id}`;
            try {
                const { message } = await api(`fasen/${phase.id}`, { method: 'PATCH', body: { status: value } });
                await this.changed();
                Alpine.store('os').toast(message);
            } catch (error) {
                Alpine.store('os').toast(errorText(error, 'Status wijzigen lukte niet. Controleer de verbinding.'));
                await this.load({ force: true });
            }
            this.busy = null;
        },
        toggleApprove(phase) {
            this.approving = this.approving === phase.id ? null : phase.id;
            this.adding = null;
            this.note = '';
        },
        async approve(phase) {
            if (this.busy) return;
            this.busy = `approve:${phase.id}`;
            try {
                const { message } = await api(`fasen/${phase.id}/vrijgeven`, { method: 'POST', body: { gate_note: this.note.trim() || null } });
                this.approving = null;
                await this.changed();
                Alpine.store('os').toast(message);
            } catch (error) {
                Alpine.store('os').toast(errorText(error, 'Vrijgeven lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
        toggleAdd(phase) {
            this.adding = this.adding === phase.id ? null : phase.id;
            this.approving = null;
            this.pkg = { name: '', responsible_id: '', deadline: '' };
            this.errors = {};
        },
        async addPackage(phase) {
            if (!this.pkg.name.trim() || this.busy) return;
            this.busy = `add:${phase.id}`;
            this.errors = {};
            try {
                const body = { project_phase_id: phase.id, name: this.pkg.name.trim(), responsible_id: this.pkg.responsible_id || null, deadline: this.pkg.deadline || null };
                const created = await api(`projecten/${id}/werkpakketten`, { method: 'POST', body });
                this.adding = null;
                fetchedAt[`project:${id}`] = fetchedAt.projecten = 0;
                fetchedAt[`uitvoering:${id}`] = 0;
                Alpine.store('os').toast('Werkpakket aangemaakt: voeg de checklist toe.');
                // Net als op de website: door naar het nieuwe werkpakket.
                location.hash = `#/werkpakket/${created.id}`;
            } catch (error) {
                this.errors = error?.data?.errors ?? {};
                if (!Object.keys(this.errors).length) Alpine.store('os').toast(errorText(error, 'Toevoegen lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
    }));
}
