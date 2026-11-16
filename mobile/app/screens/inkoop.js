// Inkoop (purchase-orders/index) en de bestelling (purchase-orders/show): bestellingen, inkoopfacturen met
// goedkeuren, afwijzen en betaald melden, een bestelling aanmaken en een factuur boeken; per bestelling de
// status, regels toevoegen en verwijderen, levering en facturen. Gegevens: api/app/inkoop
// (Api\App\PurchaseOrderController); wijzigingen via de controllers van de website. Template: inkoop.html.

export const routes = {
    screens: ['inkoop'],
    details: { inkooporder: /^\/inkoop\/(\d+)$/ },
    parents: { inkooporder: 'inkoop' },
    sections: { inkoop: 'offertes', inkooporder: 'offertes' },
    paths: { '/inkoop': 'inkoop' },
    // /inkoop?project=5 (zoals op de website) opent dit scherm met dat filter.
    query: ['inkoop'],
};

function invalidate(fetchedAt) {
    Object.keys(fetchedAt).filter((name) => /^(inkoop|financieel|nacalculatie)/.test(name)).forEach((name) => { fetchedAt[name] = 0; });
}

export default function register({ Alpine, api, screen, fetchedAt, errorText }) {
    const os = () => Alpine.store('os');

    Alpine.data('purchasing', () => {
        const base = screen('inkoop', 'inkoop');

        return {
            ...base,
            project: '',
            status: '',
            page: 1,
            busy: null,
            sheet: null,
            formError: null,
            orderForm: null,
            invoiceForm: null,
            init() {
                const { project = '', status = '' } = os().query ?? {};
                this.project = project;
                this.status = status;
                [this.cacheName, this.path] = this.target();
                return base.init.call(this);
            },
            target() {
                const query = new URLSearchParams({
                    ...(this.project ? { project: this.project } : {}),
                    ...(this.status ? { status: this.status } : {}),
                    ...(this.page > 1 ? { page: this.page } : {}),
                }).toString();
                return query ? [`inkoop:${query}`, `inkoop?${query}`] : ['inkoop', 'inkoop'];
            },
            view() {
                return this.show(...this.target());
            },
            clearFilter() {
                this.project = this.status = '';
                this.page = 1;
                history.replaceState(null, '', '#/inkoop');
                this.view();
            },
            goPage(page) {
                this.page = page;
                this.view();
                window.scrollTo(0, 0);
            },
            get projectName() {
                return this.data?.form.projects.find((project) => String(project.id) === String(this.project))?.name ?? 'project';
            },
            newOrder() {
                this.formError = null;
                this.orderForm = { project_id: this.project || (this.data?.form.projects[0]?.id ?? ''), supplier: '', category: 'materiaal', expected_on: '' };
                this.sheet = 'order';
            },
            newInvoice() {
                this.formError = null;
                this.invoiceForm = { supplier: '', number: '', project_id: this.project || '', category: 'materiaal', date: this.data?.form.today ?? '', amount: '' };
                this.sheet = 'invoice';
            },
            async storeOrder() {
                if (this.busy) return;
                this.busy = 'order';
                this.formError = null;
                try {
                    const result = await api('inkoop', { method: 'POST', body: this.orderForm });
                    invalidate(fetchedAt);
                    this.sheet = null;
                    os().toast(result.message);
                    os().go(result.path);
                } catch (error) {
                    this.formError = errorText(error, 'Aanmaken lukte niet. Controleer de verbinding.');
                }
                this.busy = null;
            },
            async storeInvoice() {
                if (this.busy) return;
                this.busy = 'invoice';
                this.formError = null;
                try {
                    const result = await api('inkoopfacturen', { method: 'POST', body: { ...this.invoiceForm, project_id: this.invoiceForm.project_id || null } });
                    invalidate(fetchedAt);
                    this.sheet = null;
                    os().toast(result.message);
                    await this.load({ force: true });
                } catch (error) {
                    this.formError = errorText(error, 'Boeken lukte niet. Controleer de verbinding.');
                }
                this.busy = null;
            },
            async setInvoiceStatus(invoice, action) {
                if (this.busy) return;
                if (action.value === 'afgewezen' && !(await os().ask(`Inkoopfactuur ${invoice.supplier} afwijzen? Hij telt dan niet mee als kosten.`, 'Afwijzen'))) return;
                this.busy = `${invoice.id}:${action.value}`;
                try {
                    const result = await api(`inkoopfacturen/${invoice.id}`, { method: 'PATCH', body: { status: action.value } });
                    invalidate(fetchedAt);
                    await this.load({ force: true });
                    os().toast(`Status: ${result.label}.`);
                } catch (error) {
                    os().toast(errorText(error, 'Opslaan lukte niet. Controleer de verbinding.'));
                }
                this.busy = null;
            },
        };
    });

    Alpine.data('purchaseOrder', (id) => ({
        ...screen(`inkooporder:${id}`, `inkoop/${id}`),
        busy: null,
        line: { description: '', quantity: '1', unit: 'st', unit_price: '' },
        async changed() {
            invalidate(fetchedAt);
            await this.load({ force: true });
        },
        async setStatus(option) {
            if (this.busy) return;
            this.busy = option.value;
            try {
                const result = await api(`inkoop/${id}`, { method: 'PATCH', body: { status: option.value } });
                await this.changed();
                os().toast(`Status: ${result.label}.`);
            } catch (error) {
                os().toast(errorText(error, 'Opslaan lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
        async addLine() {
            if (this.busy || !this.line.description.trim()) return;
            this.busy = 'line';
            try {
                await api(`inkoop/${id}/regels`, { method: 'POST', body: this.line });
                this.line = { description: '', quantity: '1', unit: 'st', unit_price: '' };
                await this.changed();
                os().toast('Regel toegevoegd.');
            } catch (error) {
                os().toast(errorText(error, 'Toevoegen lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
        async removeLine(line) {
            if (this.busy || !(await os().ask(`"${line.description}" van de bestelling halen?`, 'Verwijderen'))) return;
            this.busy = `line:${line.id}`;
            try {
                await api(`inkoopregels/${line.id}`, { method: 'DELETE' });
                await this.changed();
                os().toast('Regel verwijderd.');
            } catch (error) {
                os().toast(errorText(error, 'Verwijderen lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
    }));
}
