// Werkbonnen (work-orders.index) en een werkbon (work-orders.show): uren, materiaal, de bevroren checklist,
// foto-bewijs en de handtekening van de klant. De projectleiding keurt goed of stuurt terug (via
// WorkOrderApprovalController). Gegevens: api/app/werkbonnen (Api\App\WorkOrderController). Template: werkbonnen.html.

const API = import.meta.env.VITE_RENOVION_API;

export const routes = {
    screens: ['werkbonnen'],
    details: { werkbon: /^\/werkbonnen\/(\d+)$/ },
    parents: { werkbonnen: 'planning', werkbon: 'planning' },
    sections: { werkbonnen: 'uitvoering', werkbon: 'uitvoering' },
    paths: { '/werkbonnen': 'werkbonnen' },
    // ?status= en ?project= (vanuit Kwaliteit: de werkbonnen van één project).
    query: ['werkbonnen'],
};

const listPath = (status, project) => {
    const query = new URLSearchParams(Object.entries({ status, project }).filter(([, value]) => value)).toString();
    return { name: query ? `werkbonnen:${query}` : 'werkbonnen', path: query ? `werkbonnen?${query}` : 'werkbonnen' };
};

export default function register({ Alpine, api, screen, fetchedAt, errorText }) {
    const os = () => Alpine.store('os');

    Alpine.data('workOrders', () => {
        const status = os().query?.status ?? '';
        const project = os().query?.project ?? '';
        const start = listPath(status, project);

        return {
            ...screen(start.name, start.path),
            status,
            project,
            filter(value) {
                this.status = value;
                const next = listPath(value, this.project);
                return this.show(next.name, next.path);
            },
            allProjects() {
                this.project = '';
                const next = listPath(this.status, '');
                return this.show(next.name, next.path);
            },
        };
    });

    Alpine.data('workOrder', (id) => ({
        ...screen(`werkbon:${id}`, `werkbonnen/${id}`),
        busy: null,
        reason: '',
        viewer: null,
        photoUrl: (photo) => `${API}${photo.url}`,
        async changed(result) {
            Object.keys(fetchedAt).filter((name) => name === 'werkbonnen' || name.startsWith('werkbonnen:')).forEach((name) => { fetchedAt[name] = 0; });
            fetchedAt.inbox = fetchedAt.vandaag = fetchedAt.projecten = 0;
            await this.load({ force: true });
            if (result?.message) os().toast(result.message);
        },
        async approve() {
            if (this.busy || !(await os().ask('Werkbon goedkeuren? De uren worden als kosten op het project geboekt.', 'Goedkeuren'))) return;
            this.busy = 'approve';
            try {
                await this.changed(await api(`werkbonnen/${id}/goedkeuren`, { method: 'POST' }));
            } catch (error) {
                os().toast(errorText(error, 'Goedkeuren lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
        async reject() {
            const reason = this.reason.trim();
            if (!reason || this.busy) return;
            this.busy = 'reject';
            try {
                await this.changed(await api(`werkbonnen/${id}/afkeuren`, { method: 'POST', body: { reason } }));
                this.reason = '';
            } catch (error) {
                os().toast(errorText(error, 'Terugsturen lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
    }));
}
