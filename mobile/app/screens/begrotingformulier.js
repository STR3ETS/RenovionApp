// Nieuwe begroting (#/nieuwebegroting, ?project=5 of ?aanvraag=3 kiest het dossier), zoals budgets/create:
// naam, dossier, uurtarief, btw, oppervlak, skelet en locatie. Hoofdstukken, staart en betaalschema zet de
// server klaar. Gegevens: api/app/formulieren/begrotingen/nieuw; opslaan via BudgetController@store.
import { filled, formScreen, queryOf, querySuffix } from './forms/form.js';

export const routes = {
    screens: ['nieuwebegroting'],
    parents: { nieuwebegroting: 'projecten' },
    sections: { nieuwebegroting: 'offertes' },
    paths: { '/begrotingen/nieuw': 'nieuwebegroting' },
    query: ['nieuwebegroting'],
};

export default function register(context) {
    const { Alpine } = context;

    Alpine.data('budgetCreateForm', () => {
        const suffix = querySuffix(queryOf(Alpine), ['project', 'aanvraag']);
        return {
            ...formScreen(context, {
                name: `formulier:begroting:nieuw${suffix}`,
                path: `formulieren/begrotingen/nieuw${suffix}`,
                fill: (data) => ({
                    name: '',
                    project_id: data.values.project_id ?? '',
                    lead_id: data.values.lead_id ?? '',
                    hourly_rate: data.values.hourly_rate,
                    vat_pct: data.values.vat_pct,
                    floor_area: '',
                    template: '',
                    location: '',
                }),
            }),
            save() {
                const refresh = ['begrotingen', ...(this.form.project_id ? [`project:${this.form.project_id}`] : [])];
                return this.submit('formulieren/begrotingen', filled(this.form), { success: 'Begroting aangemaakt.', refresh });
            },
        };
    });
}
