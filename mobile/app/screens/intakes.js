// Intakes: aanvragen zonder intake (starten), lopende en afgeronde intakes (intakes/index), en de intake zelf in vijf
// stappen (intakes/show): antwoorden per stap opslaan, opname inplannen, samenvatting en afronden.
// Gegevens: api/app/intakes en api/app/intakes/{id} (Api\App\IntakeController); opslaan via de IntakeController van
// de website, die JSON geeft aan de app. Template: intakes.html.

export const routes = {
    screens: ['intakes'],
    details: { intake: /^\/intakes\/(\d+)$/ },
    parents: { intake: 'intakes' },
    sections: { intakes: 'verkoop', intake: 'verkoop' },
    paths: { '/intakes': 'intakes' },
};

export default function register({ Alpine, api, screen, fetchedAt, errorText }) {
    Alpine.data('intakes', () => ({
        ...screen('intakes', 'intakes'),
        busy: null,
        async start(lead) {
            if (this.busy) return;
            this.busy = lead.id;
            try {
                const result = await api('intakes', { method: 'POST', body: { lead_id: lead.id } });
                fetchedAt.intakes = fetchedAt.aanvragen = fetchedAt.opvolging = 0;
                Alpine.store('os').toast('Intake geopend. Loop de stappen langs.');
                Alpine.store('os').go(result.path);
            } catch (error) {
                Alpine.store('os').toast(errorText(error, 'Intake starten lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
    }));

    Alpine.data('intake', (id) => ({
        ...screen(`intake:${id}`, `intakes/${id}`),
        tab: null, // de stap die je bekijkt (standaard de stap waar de intake staat)
        form: { answers: {}, summary: '' },
        survey: { surveyor_id: '', survey_on: '', survey_at: '' },
        dirty: false, // iets ingetypt: dan niet overschrijven met wat er van de server komt
        busy: null,
        // Bij nieuwe gegevens van de server (x-effect in de template): de velden vullen, tenzij je aan het typen bent.
        sync() {
            if (!this.data || this.dirty) return;
            this.tab ??= Math.min(this.data.steps.length - 1, Math.max(0, this.data.step));
            this.form = { answers: { ...this.data.answers }, summary: this.data.summary ?? '' };
            this.survey = {
                surveyor_id: String(this.data.survey.surveyor_id ?? this.data.surveyors[0]?.id ?? ''),
                survey_on: this.data.survey.on ?? '',
                survey_at: this.data.survey.at ?? '',
            };
        },
        get fields() {
            return (this.data?.fields ?? []).filter((field) => field.step === this.tab);
        },
        open(step) {
            this.tab = step;
            this.dirty = false;
            this.sync();
        },
        async changed() {
            fetchedAt.intakes = fetchedAt.aanvragen = fetchedAt.opvolging = 0;
            this.dirty = false;
            await this.load({ force: true });
            this.sync();
        },
        // Een stap opslaan: de antwoorden van die stap (stap 5 ook de samenvatting), daarna door naar de volgende.
        async save() {
            if (this.busy) return;
            this.busy = 'save';
            const step = this.tab;
            const answers = Object.fromEntries(this.fields.map((field) => [field.key, this.form.answers[field.key] ?? '']));
            const body = { step, ...(this.fields.length ? { answers } : {}), ...(step === 4 ? { summary: this.form.summary } : {}) };
            try {
                const result = await api(`intakes/${id}`, { method: 'PATCH', body });
                Alpine.store('os').toast(result.message);
                this.tab = result.step;
                await this.changed();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch (error) {
                Alpine.store('os').toast(errorText(error, 'Opslaan lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
        async planSurvey() {
            if (this.busy) return;
            this.busy = 'survey';
            try {
                const result = await api(`intakes/${id}/opname`, {
                    method: 'POST',
                    body: { surveyor_id: this.survey.surveyor_id, survey_on: this.survey.survey_on, survey_at: this.survey.survey_at || null },
                });
                fetchedAt.planning = fetchedAt.vandaag = 0;
                Alpine.store('os').toast(result.message);
                await this.changed();
            } catch (error) {
                Alpine.store('os').toast(errorText(error, 'Opname plannen lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
        async complete() {
            if (this.busy) return;
            this.busy = 'complete';
            try {
                const result = await api(`intakes/${id}/afronden`, { method: 'POST' });
                Alpine.store('os').toast(result.message);
                await this.changed();
            } catch (error) {
                Alpine.store('os').toast(errorText(error, 'Afronden lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
    }));
}
