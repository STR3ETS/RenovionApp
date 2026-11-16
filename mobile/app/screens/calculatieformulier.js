// Nieuwe calculatie (#/nieuwecalculatie, ?aanvraag=5 koppelt de aanvraag en vult titel, klant en omschrijving),
// zoals calculations/create: handmatig (lege calculatie, regels daarna) of met een AI-voorstel van Nova dat je
// eerst controleert (regels weglaten kan) voordat de calculatie met die regels wordt aangemaakt.
// Gegevens: api/app/formulieren/calculaties/nieuw (Api\App\FormController); opslaan via CalculationController@store.
import { dictation, filled, formScreen, queryOf, querySuffix } from './forms/form.js';

export const routes = {
    screens: ['nieuwecalculatie'],
    parents: { nieuwecalculatie: 'aanvragen' },
    sections: { nieuwecalculatie: 'offertes' },
    paths: { '/calculaties/nieuw': 'nieuwecalculatie' },
    query: ['nieuwecalculatie'],
};

export default function register(context) {
    const { Alpine, api, errorText } = context;

    Alpine.data('calculationCreateForm', () => {
        const suffix = querySuffix(queryOf(Alpine), ['aanvraag']);
        return {
            ...formScreen(context, {
                name: `formulier:calculatie:nieuw${suffix}`,
                path: `formulieren/calculaties/nieuw${suffix}`,
                fill: (data) => ({
                    ...data.values,
                    customer_id: data.values.customer_id ?? '',
                    lead_id: data.values.lead_id ?? '',
                    sources: data.sources.map((source) => source.source),
                }),
            }),
            ...dictation(),
            mode: 'handmatig',
            busy: false,
            proposeError: null,
            proposal: null,
            lineTotal(line) {
                return (Number(line.quantity) || 0) * (Number(line.unit_price) || 0) * (1 + (Number(line.surcharge_pct) || 0) / 100);
            },
            proposalTotal() {
                return (this.proposal?.lines ?? []).reduce((sum, line) => sum + this.lineTotal(line), 0);
            },
            toggleSource(source) {
                this.form.sources = this.form.sources.includes(source)
                    ? this.form.sources.filter((item) => item !== source)
                    : [...this.form.sources, source];
            },
            removeLine(index) {
                this.proposal.lines.splice(index, 1);
                if (!this.proposal.lines.length) this.proposal = null;
            },
            async propose() {
                if (this.busy || !this.form.description.trim()) return;
                this.busy = true;
                this.proposeError = null;
                this.proposal = null;
                try {
                    this.proposal = await api('formulieren/calculaties/ai-voorstel', { method: 'POST', body: { description: this.form.description, sources: this.form.sources } });
                    if (!this.proposal?.lines?.length) {
                        this.proposal = null;
                        this.proposeError = 'Nova vond geen regels. Beschrijf het werk iets uitgebreider.';
                    }
                } catch (error) {
                    this.proposeError = errorText(error, 'Nova kon geen regels voorstellen. Controleer de verbinding.');
                }
                this.busy = false;
            },
            save() {
                const { sources: _sources, ...values } = this.form;
                const body = filled(values);
                if (this.mode === 'ai' && this.proposal) body.lines = this.proposal.lines;
                const refresh = ['aanvragen', 'klanten', ...(values.lead_id ? [`aanvraag:${values.lead_id}`] : []), ...(values.customer_id ? [`klant:${values.customer_id}`] : [])];
                return this.submit('formulieren/calculaties', body, { success: 'Calculatie aangemaakt. Controleer de regels en stel bij waar nodig.', refresh });
            },
        };
    });
}
