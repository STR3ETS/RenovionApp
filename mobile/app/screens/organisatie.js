// Organisatie-instellingen (settings/index): bedrijfsgegevens en de standaardwaarden waarmee het systeem rekent,
// of de geheimen in .env er zijn, welke modules aan staan en de hulplinks. Alleen directie (manage-team).
// Niet de meldingen: dat is het scherm 'instellingen'. Opslaan in één keer via SettingController@update, net als
// het formulier op de website. Gegevens: api/app/organisatie (Api\App\CompanySettingsController).
// Template: organisatie.html.

export const routes = {
    screens: ['organisatie'],
    parents: { organisatie: 'instellingen' },
    sections: { organisatie: 'organisatie' },
    paths: { '/instellingen': 'organisatie' },
};

export default function register({ Alpine, api, screen, errorText }) {
    const os = () => Alpine.store('os');

    Alpine.data('companySettings', () => ({
        ...screen('organisatie', 'organisatie'),
        values: {},
        saving: false,
        // Het formulier volgt de server tot je iets wijzigt; een verse lading overschrijft je invoer niet.
        value(field) {
            return field.key in this.values ? this.values[field.key] : (field.value ?? '');
        },
        set(field, value) {
            this.values = { ...this.values, [field.key]: value };
        },
        get dirty() {
            const fields = (this.data?.groups ?? []).flatMap((group) => group.fields);
            return fields.some((field) => field.key in this.values && String(this.values[field.key]) !== String(field.value ?? ''));
        },
        async save() {
            if (this.saving || !this.data) return;
            this.saving = true;
            const settings = Object.fromEntries(this.data.groups.flatMap((group) => group.fields).map((field) => [field.key, String(this.value(field))]));
            try {
                const { message } = await api('organisatie', { method: 'PATCH', body: { settings } });
                await this.load({ force: true });
                this.values = {};
                os().toast(message);
            } catch (error) {
                os().toast(errorText(error, 'Opslaan lukte niet. Controleer de verbinding.'));
            }
            this.saving = false;
        },
    }));
}
