// Nova en de agents: Goedkeuringen (agents/approvals, besluiten met "Ja, doe maar" of "Eerst aanpassen"),
// Uitvoeringen (agents/runs, filter op status en agent, verloop per uitvoering) en Skills (agents/skills,
// niveau, bedragsgrens en pauze per skill; alleen directie). Gegevens: api/app/goedkeuringen, agents en skills
// (Api\App\AgentController); besluiten en skills opslaan via de AgentController van de website.
// Template: agents.html.

// Kleurklassen die de API meegeeft buiten app/Enums (AppColours::chip, AgentApproval/AgentRun::appBadgeClasses),
// hier voluit zodat Tailwind ze in de app-build zet (mobile/app/app.css scant alleen app/Enums):
// bg-brand-100 text-brand-700 bg-lead-100 text-lead-700 bg-calc-100 text-calc-700 bg-klant-100 text-klant-700
// bg-offerte-100 text-offerte-700 bg-project-100 text-project-700 bg-planning-100 text-planning-700
// bg-amber-100 text-amber-800 bg-green-100 text-green-800 bg-red-100 text-red-800 bg-gray-100 bg-gray-200 text-gray-700

export const routes = {
    screens: ['goedkeuringen', 'agents', 'skills'],
    parents: { goedkeuringen: 'inbox', agents: 'inbox' },
    sections: { goedkeuringen: 'inbox', agents: 'organisatie', skills: 'organisatie' },
    paths: { '/goedkeuringen': 'goedkeuringen', '/agents': 'agents', '/skills': 'skills' },
};

// Een besluit verandert de goedkeuringen, de uitvoeringen en wat de inbox en Vandaag tonen.
function invalidate(fetchedAt) {
    Object.keys(fetchedAt).filter((name) => /^(goedkeuringen|agents)/.test(name)).forEach((name) => { fetchedAt[name] = 0; });
    fetchedAt.inbox = fetchedAt.vandaag = 0;
}

export default function register({ Alpine, api, screen, fetchedAt, errorText }) {
    const os = () => Alpine.store('os');

    Alpine.data('approvals', () => ({
        ...screen('goedkeuringen', 'goedkeuringen'),
        busy: null,
        reasons: {},
        async decide(approval, decision) {
            if (this.busy) return;
            if (decision === 'goedkeuren' && !(await os().ask(`${approval.action}: goedkeuren en vrijgeven?`, 'Ja, doe maar'))) return;
            this.busy = `${approval.id}:${decision}`;
            try {
                const reason = (this.reasons[approval.id] ?? '').trim();
                const result = await api(`goedkeuringen/${approval.id}`, { method: 'POST', body: { besluit: decision, ...(reason ? { reden: reason } : {}) } });
                delete this.reasons[approval.id];
                invalidate(fetchedAt);
                await this.load({ force: true });
                os().toast(result.message);
            } catch (error) {
                os().toast(errorText(error, 'Besluiten lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
    }));

    Alpine.data('agentRuns', () => ({
        ...screen('agents', 'agents'),
        status: 'actief',
        agent: '',
        page: 1,
        open: null,
        view() {
            const params = { ...(this.status !== 'actief' ? { status: this.status } : {}), ...(this.agent ? { agent: this.agent } : {}), ...(this.page > 1 ? { page: this.page } : {}) };
            const query = new URLSearchParams(params).toString();
            this.open = null;
            return this.show(query ? `agents:${query}` : 'agents', query ? `agents?${query}` : 'agents');
        },
        filter(status) {
            if (this.status === status) return;
            this.status = status;
            this.page = 1;
            this.view();
        },
        pickAgent(id) {
            this.agent = this.agent === id ? '' : id;
            this.page = 1;
            this.view();
        },
        goPage(page) {
            this.page = page;
            this.view();
            window.scrollTo(0, 0);
        },
        toggle(run) {
            this.open = this.open === run.id ? null : run.id;
        },
    }));

    Alpine.data('agentSkills', () => ({
        ...screen('skills', 'skills'),
        busy: null,
        // form = het formulier van één skill (x-data per skill in de template, begint bij de stand van de server).
        changed(skill, form) {
            return form.level !== skill.level || String(form.limit ?? '') !== String(skill.limit ?? '') || form.paused !== skill.paused;
        },
        async save(skill, form) {
            if (this.busy) return;
            this.busy = skill.id;
            try {
                const limit = String(form.limit ?? '').trim().replace(',', '.');
                const result = await api(`skills/${skill.id}`, { method: 'PATCH', body: { level: form.level, paused: form.paused, limit_amount: limit === '' ? null : limit } });
                form.limit = limit === '' ? '' : Number(limit);
                await this.load({ force: true });
                os().toast(result.message);
            } catch (error) {
                os().toast(errorText(error, 'Opslaan lukte niet. Controleer de verbinding.'));
            }
            this.busy = null;
        },
    }));
}
