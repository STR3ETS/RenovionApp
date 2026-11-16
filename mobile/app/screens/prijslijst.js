// Prijslijst: de kostendatabase waar Nova calculatie- en offerteregels uit voorstelt.
// Gegevens: api/app/prijslijst (Api\App\PriceListController), alles in één keer; zoeken en het typefilter
// gebeuren op de telefoon zelf. Toevoegen, bewerken en aan/uit via PriceListController van de website.
// Template: prijslijst.html.

export const routes = {
    screens: ['prijslijst'],
    sections: { prijslijst: 'offertes' },
    paths: { '/prijslijst': 'prijslijst' },
};

const EMPTY = () => ({ name: '', type: 'arbeid', unit: 'm2', unit_price: '' });

export default function register({ Alpine, api, screen, errorText }) {
    Alpine.data('priceList', () => ({
        ...screen('prijslijst', 'prijslijst'),
        q: '',
        type: '',
        adding: false,
        item: EMPTY(),
        editing: null,
        edit: {},
        busy: null,
        get shown() {
            const q = this.q.trim().toLowerCase();
            return (this.data?.items ?? []).filter((item) => (!this.type || item.type.value === this.type)
                && (!q || item.name.toLowerCase().includes(q) || (item.code ?? '').toLowerCase().includes(q)));
        },
        async refresh(message) {
            await this.load({ force: true });
            if (message) Alpine.store('os').toast(message);
        },
        async add() {
            if (this.busy || !this.item.name.trim() || this.item.unit_price === '') return;
            this.busy = 'add';
            try {
                const { message } = await api('prijslijst', { method: 'POST', body: this.item });
                this.item = EMPTY();
                this.adding = false;
                await this.refresh(message);
            } catch (error) {
                Alpine.store('os').toast(errorText(error, 'Toevoegen lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
        startEdit(item) {
            this.editing = item.id;
            this.edit = { name: item.name, type: item.type.value, unit: item.unit, unit_price: item.unit_price, surcharge_pct: item.surcharge_pct };
        },
        async save(item) {
            if (this.busy) return;
            this.busy = `save:${item.id}`;
            try {
                const { message } = await api(`prijslijst/${item.id}`, { method: 'PATCH', body: this.edit });
                this.editing = null;
                await this.refresh(message);
            } catch (error) {
                Alpine.store('os').toast(errorText(error, 'Opslaan lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
        async toggle(item) {
            if (this.busy) return;
            this.busy = `toggle:${item.id}`;
            const before = item.active;
            item.active = !before;
            try {
                const { message } = await api(`prijslijst/${item.id}`, { method: 'PATCH', body: { toggle: 1 } });
                await this.refresh(message);
            } catch (error) {
                item.active = before;
                Alpine.store('os').toast(errorText(error, 'Aan- of uitzetten lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
    }));
}
