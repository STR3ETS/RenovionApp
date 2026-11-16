// Offerte aanmaken (#/nieuweofferte, ?aanvraag=5 koppelt de aanvraag en kiest de klant) en de posten van een
// conceptofferte bewerken (#/offerteposten/5): regels, eenheden, btw, stelposten, uit de prijslijst kiezen en
// Nova laten voorstellen, zoals quotes/create en quotes/edit (line-editor) op de website.
// Gegevens: api/app/formulieren/offertes/* (Api\App\FormController); opslaan via QuoteController@store/update.
import { dictation, filled, formScreen, queryOf, querySuffix } from './forms/form.js';

export const routes = {
    screens: ['nieuweofferte'],
    details: { offerteposten: /^\/offertes\/(\d+)\/edit$/ },
    parents: { nieuweofferte: 'aanvragen', offerteposten: 'aanvragen' },
    sections: { nieuweofferte: 'offertes', offerteposten: 'offertes' },
    paths: { '/offertes/create': 'nieuweofferte' },
    query: ['nieuweofferte'],
};

const UNITS = ['m²', 'm¹', 'uur', 'stuks', 'post'];
// Eenheden uit de prijslijst naar die van de offerte (zoals de line-editor op de website).
const PRICE_UNITS = { m2: 'm²', m1: 'm¹', uur: 'uur', stuk: 'stuks', stuks: 'stuks', post: 'post', 'm²': 'm²', 'm¹': 'm¹' };
const REFRESH = ['aanvragen', 'offertes', 'inbox', 'vandaag', 'klanten'];

let lineKey = 0;
const withKey = (line) => ({ ...line, key: ++lineKey });

export default function register(context) {
    const { Alpine, api, errorText } = context;

    Alpine.data('quoteCreateForm', () => {
        const suffix = querySuffix(queryOf(Alpine), ['aanvraag']);
        return {
            ...formScreen(context, {
                name: `formulier:offerte:nieuw${suffix}`,
                path: `formulieren/offertes/nieuw${suffix}`,
                fill: (data) => ({ customer_id: data.lead?.customer_id ?? '', lead_id: data.lead?.id ?? '', valid_until: data.valid_until, notes: '' }),
            }),
            save() {
                const refresh = this.form.lead_id ? [...REFRESH, `aanvraag:${this.form.lead_id}`] : REFRESH;
                return this.submit('formulieren/offertes', filled(this.form), { success: 'Offerte aangemaakt. Bouw hem verder op met de offerte-builder.', refresh });
            },
        };
    });

    Alpine.data('quoteLinesForm', (id) => ({
        ...formScreen(context, {
            name: `formulier:offerte:${id}`,
            path: `formulieren/offertes/${id}`,
            fill: (data) => {
                const lines = data.values.lines.map(withKey);
                return { valid_until: data.values.valid_until ?? '', notes: data.values.notes ?? '', lines: lines.length ? lines : [withKey(blankLine())] };
            },
        }),
        ...dictation(),
        units: UNITS,
        // Prijslijst (sheet) en Nova-voorstel
        picker: false,
        pickerQ: '',
        pickerItems: [],
        pickerLoading: false,
        pickerTimer: null,
        nova: { open: false, prompt: '', loading: false, error: null, note: null },

        lineTotal(line) {
            return (parseFloat(line.quantity) || 0) * (parseFloat(line.unit_price) || 0);
        },
        subtotal() {
            return (this.form?.lines ?? []).reduce((sum, line) => sum + this.lineTotal(line), 0);
        },
        vat() {
            return (this.form?.lines ?? []).reduce((sum, line) => sum + this.lineTotal(line) * ((parseFloat(line.vat_rate) || 0) / 100), 0);
        },
        add() {
            this.form.lines.push(withKey(blankLine()));
            this.dirty = true;
            this.$nextTick(() => this.$el.querySelector(`[data-line="${this.form.lines.at(-1).key}"] input`)?.focus());
        },
        remove(index) {
            this.form.lines.splice(index, 1);
            this.dirty = true;
            // De fouten horen bij regelnummers, die schuiven nu op.
            this.errors = Object.fromEntries(Object.entries(this.errors).filter(([field]) => !field.startsWith('lines.')));
        },
        // Nieuwe regels erbij zonder lege invoerregels te laten staan.
        append(lines) {
            this.form.lines = this.form.lines.filter((line) => (line.description ?? '').trim() !== '' || parseFloat(line.unit_price) > 0);
            this.form.lines.push(...lines.map(withKey));
            if (!this.form.lines.length) this.form.lines.push(withKey(blankLine()));
            this.dirty = true;
        },
        openPicker() {
            this.picker = true;
            this.search();
            this.$nextTick(() => this.$refs.pickerInput?.focus());
        },
        searchSoon() {
            clearTimeout(this.pickerTimer);
            this.pickerTimer = setTimeout(() => this.search(), 300);
        },
        async search() {
            this.pickerLoading = true;
            try {
                this.pickerItems = (await api(`formulieren/prijsitems?q=${encodeURIComponent(this.pickerQ)}`)).items;
            } catch {
                this.pickerItems = [];
            }
            this.pickerLoading = false;
        },
        choose(item) {
            this.append([{
                description: item.name,
                quantity: 1,
                unit: PRICE_UNITS[item.unit] ?? 'post',
                unit_price: Math.round(item.price * (1 + (item.surcharge_pct || 0) / 100) * 100) / 100,
                vat_rate: 21,
                is_estimate: item.type === 'stelpost',
            }]);
            this.picker = false;
            this.pickerQ = '';
            this.pickerItems = [];
        },
        async propose() {
            if (this.nova.loading || !this.nova.prompt.trim()) return;
            this.nova.loading = true;
            this.nova.error = this.nova.note = null;
            try {
                const result = await api(`formulieren/offertes/${id}/posten-voorstel`, { method: 'POST', body: { description: this.nova.prompt } });
                this.append(result.lines);
                this.nova.note = result.note;
                this.nova.prompt = '';
            } catch (error) {
                this.nova.error = error.data?.error ?? errorText(error, 'Nova kon geen posten voorstellen. Controleer de verbinding.');
            }
            this.nova.loading = false;
        },
        save() {
            const body = {
                valid_until: this.form.valid_until,
                notes: this.form.notes,
                lines: this.form.lines.map(({ key: _key, ...line }) => ({ ...line, is_estimate: Boolean(line.is_estimate) })),
            };
            return this.submit(`formulieren/offertes/${id}`, body, { method: 'PATCH', success: 'Offerte bijgewerkt.', refresh: [...REFRESH, `offerte:${id}`] });
        },
        destroy() {
            clearTimeout(this.pickerTimer);
            this.recognition?.abort?.();
        },
    }));
}

function blankLine() {
    return { description: '', quantity: 1, unit: 'm²', unit_price: '', vat_rate: 21, is_estimate: false };
}
