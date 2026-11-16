// Aanvraag aanmaken (#/nieuweaanvraag, ?klant=3 kiest de bestaande klant) en bewerken (#/aanvraagbewerken/5),
// met dezelfde velden en regels als leads/create en leads/edit op de website.
// Gegevens: api/app/formulieren/aanvragen/* (Api\App\FormController); opslaan via LeadController@store/update.
import { filled, formScreen, queryOf, querySuffix } from './forms/form.js';

export const routes = {
    screens: ['nieuweaanvraag'],
    details: { aanvraagbewerken: /^\/aanvragen\/(\d+)\/edit$/ },
    parents: { nieuweaanvraag: 'aanvragen', aanvraagbewerken: 'aanvragen' },
    sections: { nieuweaanvraag: 'verkoop', aanvraagbewerken: 'verkoop' },
    paths: { '/aanvragen/create': 'nieuweaanvraag' },
    query: ['nieuweaanvraag'],
};

const REFRESH = ['aanvragen', 'inbox', 'vandaag', 'klanten'];

export default function register(context) {
    const { Alpine } = context;

    Alpine.data('leadCreateForm', () => {
        const suffix = querySuffix(queryOf(Alpine), ['klant']);
        return {
            ...formScreen(context, {
                name: `formulier:aanvraag:nieuw${suffix}`,
                path: `formulieren/aanvragen/nieuw${suffix}`,
                fill: (data) => ({
                    existing: Boolean(data.customer_id),
                    customer_id: data.customer_id ?? '',
                    name: '', email: '', phone: '', address: '', postal_code: '', city: '',
                    service: '', value: '', desired_start: '', description: '', assigned_to: '', next_action_at: '', next_action: '',
                }),
            }),
            save() {
                const { existing, customer_id, name, email, phone, address, postal_code, city, ...lead } = this.form;
                // Zoals op de website: met "Bestaande klant" gaat alleen de klant mee, anders de nieuwe klantgegevens.
                const body = existing ? { customer_id, ...lead } : { name, email, phone, address, postal_code, city, ...lead };
                const refresh = existing && customer_id ? [...REFRESH, `klant:${customer_id}`] : REFRESH;
                return this.submit('formulieren/aanvragen', filled(body), { success: 'Aanvraag aangemaakt.', refresh });
            },
        };
    });

    Alpine.data('leadEditForm', (id) => ({
        ...formScreen(context, {
            name: `formulier:aanvraag:${id}`,
            path: `formulieren/aanvragen/${id}`,
            fill: (data) => ({ ...data.values, value: data.values.value ?? '', assigned_to: data.values.assigned_to ?? '' }),
        }),
        save() {
            return this.submit(`formulieren/aanvragen/${id}`, this.form, { method: 'PATCH', success: 'Aanvraag bijgewerkt.', refresh: [...REFRESH, `aanvraag:${id}`] });
        },
    }));
}
