// Nieuwe prijsaanvraag (#/nieuweprijsaanvraag, ?project=5 kiest het project), zoals rfqs/create: project,
// wat je uitzet, hoofdstuk uit de begroting (leeg budget = het hoofdstuktotaal), deadline, start en omschrijving.
// Gegevens: api/app/formulieren/prijsaanvragen/nieuw; opslaan via RfqController@store.
import { filled, formScreen, queryOf, querySuffix } from './forms/form.js';

export const routes = {
    screens: ['nieuweprijsaanvraag'],
    parents: { nieuweprijsaanvraag: 'projecten' },
    sections: { nieuweprijsaanvraag: 'mensen' },
    paths: { '/prijsaanvragen/nieuw': 'nieuweprijsaanvraag' },
    query: ['nieuweprijsaanvraag'],
};

export default function register(context) {
    const { Alpine } = context;

    Alpine.data('rfqCreateForm', () => {
        const suffix = querySuffix(queryOf(Alpine), ['project']);
        return {
            ...formScreen(context, {
                name: `formulier:prijsaanvraag:nieuw${suffix}`,
                path: `formulieren/prijsaanvragen/nieuw${suffix}`,
                fill: (data) => ({
                    project_id: data.values.project_id ?? '',
                    title: '',
                    chapter_code: '',
                    budget: '',
                    deadline: data.values.deadline,
                    starts_on: '',
                    scope: '',
                }),
            }),
            save() {
                return this.submit('formulieren/prijsaanvragen', filled(this.form), { success: 'Prijsaanvraag aangemaakt.', refresh: ['prijsaanvragen'] });
            },
        };
    });
}
