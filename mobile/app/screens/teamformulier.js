// Teamlid toevoegen (#/nieuwteamlid) en bewerken (#/teamlidbewerken/5), alleen voor wie het team beheert
// (manage-team), zoals team/create en team/edit: naam, e-mail, telefoon, rol en wachtwoord. Je eigen rol kun je
// niet wijzigen. Gegevens: api/app/formulieren/team/*; opslaan via TeamController@store/update.
import { filled, formScreen } from './forms/form.js';

export const routes = {
    screens: ['nieuwteamlid'],
    details: { teamlidbewerken: /^\/team\/(\d+)\/bewerken$/ },
    parents: { nieuwteamlid: 'instellingen', teamlidbewerken: 'instellingen' },
    sections: { nieuwteamlid: 'organisatie', teamlidbewerken: 'organisatie' },
    paths: { '/team/aanmaken': 'nieuwteamlid' },
};

export default function register(context) {
    const { Alpine } = context;

    Alpine.data('teamCreateForm', () => ({
        ...formScreen(context, {
            name: 'formulier:team:nieuw',
            path: 'formulieren/team/nieuw',
            fill: (data) => ({ name: '', email: '', phone: '', role: data.values.role, password: '' }),
        }),
        showPassword: false,
        save() {
            return this.submit('formulieren/team', filled(this.form), { success: `${this.form.name.trim()} is toegevoegd aan het team.`, refresh: ['team', 'mensen', 'planning', 'taken'] });
        },
    }));

    Alpine.data('teamEditForm', (id) => ({
        ...formScreen(context, {
            name: `formulier:team:${id}`,
            path: `formulieren/team/${id}`,
            fill: (data) => ({ ...data.values, phone: data.values.phone ?? '', password: '' }),
        }),
        showPassword: false,
        save() {
            return this.submit(`formulieren/team/${id}`, this.form, { method: 'PATCH', success: `${this.form.name.trim()} is bijgewerkt.`, refresh: ['team', 'mensen', 'planning', 'taken'] });
        },
    }));
}
