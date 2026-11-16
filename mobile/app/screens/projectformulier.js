// Project aanmaken (#/nieuwproject) en bewerken (#/projectbewerken/5), met dezelfde velden en regels als
// projects/create en projects/edit: klant (bestaand of nieuw), planning, projectleider, uitvoerders, fase,
// betalingen en een omslagfoto (eerst verkleind, dan als FormData).
// Gegevens: api/app/formulieren/projecten/* (Api\App\FormController); opslaan via ProjectController@store/update.
import { filled, formScreen } from './forms/form.js';

export const routes = {
    screens: ['nieuwproject'],
    details: { projectbewerken: /^\/projecten\/(\d+)\/bewerken$/ },
    parents: { nieuwproject: 'projecten', projectbewerken: 'projecten' },
    sections: { nieuwproject: 'projecten', projectbewerken: 'projecten' },
    paths: { '/projecten/aanmaken': 'nieuwproject' },
};

const REFRESH = ['projecten', 'vandaag', 'planning', 'klanten'];

// Velden als FormData (nodig voor de foto). Een PATCH gaat als POST met _method: PHP leest multipart alleen bij POST.
function formData(values, method = null) {
    const data = new FormData();
    if (method) data.append('_method', method);
    for (const [key, value] of Object.entries(values)) {
        if (Array.isArray(value)) {
            // Lege lijst: het veld moet er wel zijn (= niemand), Laravel maakt van '' null.
            if (!value.length) data.append(key, '');
            value.forEach((item) => data.append(`${key}[]`, item));
        } else if (value instanceof Blob) {
            data.append(key, value, value.name ?? 'foto.jpg');
        } else if (typeof value === 'boolean') {
            data.append(key, value ? '1' : '0');
        } else {
            data.append(key, value ?? '');
        }
    }
    return data;
}

function coverPicker(shrinkPhoto) {
    return {
        cover: null,
        coverPreview: null,
        async pickCover(event) {
            const file = event.target.files?.[0];
            event.target.value = '';
            if (!file) return;
            this.cover = await shrinkPhoto(file);
            if (this.coverPreview) URL.revokeObjectURL(this.coverPreview);
            this.coverPreview = URL.createObjectURL(this.cover);
            this.dirty = true;
            const { cover_photo: _removed, ...rest } = this.errors;
            this.errors = rest;
        },
        clearCover() {
            if (this.coverPreview) URL.revokeObjectURL(this.coverPreview);
            this.cover = this.coverPreview = null;
        },
        // Uitvoerders aan- en uitvinken (craftsmen[] op de website).
        toggleCraftsman(userId) {
            this.form.craftsmen = this.form.craftsmen.includes(userId)
                ? this.form.craftsmen.filter((item) => item !== userId)
                : [...this.form.craftsmen, userId];
            this.dirty = true;
        },
        destroy() {
            if (this.coverPreview) URL.revokeObjectURL(this.coverPreview);
        },
    };
}

export default function register(context) {
    const { Alpine, shrinkPhoto } = context;

    Alpine.data('projectCreateForm', () => ({
        ...formScreen(context, {
            name: 'formulier:project:nieuw',
            path: 'formulieren/projecten/nieuw',
            fill: () => ({
                existing: true,
                customer_id: '',
                customer_name: '', customer_email: '', customer_phone: '', customer_address: '', customer_postal_code: '', customer_city: '',
                name: '', address: '', city: '', status: 'voorbereiding', project_leader_id: '',
                start_date: '', end_date_expected: '', value: '', deposit_amount: '', notes: '', craftsmen: [],
            }),
        }),
        ...coverPicker(shrinkPhoto),
        save() {
            const { existing, customer_id, customer_name, customer_email, customer_phone, customer_address, customer_postal_code, customer_city, ...project } = this.form;
            const customer = existing ? { customer_id } : { customer_name, customer_email, customer_phone, customer_address, customer_postal_code, customer_city };
            const values = filled({ ...customer, ...project });
            const body = this.cover ? formData({ ...values, cover_photo: this.cover }) : values;
            return this.submit('formulieren/projecten', body, { success: 'Project aangemaakt.', refresh: REFRESH });
        },
    }));

    Alpine.data('projectEditForm', (id) => ({
        ...formScreen(context, {
            name: `formulier:project:${id}`,
            path: `formulieren/projecten/${id}`,
            fill: (data) => {
                const values = { ...data.values };
                ['value', 'deposit_amount', 'paid_amount', 'project_leader_id'].forEach((key) => { values[key] = values[key] ?? ''; });
                values.current_phase = values.current_phase ?? '';
                values.craftsmen = (values.craftsmen ?? []).map(Number);
                return values;
            },
        }),
        ...coverPicker(shrinkPhoto),
        save() {
            const values = { ...this.form };
            // Zonder fasen (kan niet, maar dan ook niet versturen): current_phase moet een getal zijn.
            if (values.current_phase === '' || values.current_phase === null) delete values.current_phase;
            const refresh = [...REFRESH, `project:${id}`];
            const options = { success: 'Project bijgewerkt.', refresh };
            return this.cover
                ? this.submit(`formulieren/projecten/${id}`, formData({ ...values, cover_photo: this.cover }, 'PATCH'), options)
                : this.submit(`formulieren/projecten/${id}`, values, { ...options, method: 'PATCH' });
        },
    }));
}
