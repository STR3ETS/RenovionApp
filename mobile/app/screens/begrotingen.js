// Begrotingen: de lijst (concept/definitief, zoeken) en de calculatietafel van één begroting: hoofdstukken met
// paragrafen en regels, de staart, opties en meerwerk, het betaalschema en de uitgangspunten.
// Gegevens: api/app/begrotingen (Api\App\BudgetController). Elke wijziging geeft de hele begroting terug.
// Template: begrotingen.html. Een nieuwe begroting is een formulier (go('/begrotingen/nieuw')).

export const routes = {
    screens: ['begrotingen'],
    details: { begroting: /^\/begrotingen\/(\d+)$/ },
    parents: { begroting: 'begrotingen' },
    sections: { begrotingen: 'offertes', begroting: 'offertes' },
    paths: { '/begrotingen': 'begrotingen' },
};

// Getalvelden van een regel: leeg betekent nul (de server wil tekst, "1.234,56" of "1234.56").
const LINE_NUMBERS = ['quantity', 'norm_hours', 'material', 'equipment', 'subcontracting', 'other'];

export default function register({ Alpine, api, screen, fetchedAt, cache, errorText }) {
    Alpine.data('budgets', () => ({
        ...screen('begrotingen', 'begrotingen'),
        status: '',
        q: '',
        filters: [['', 'Alles'], ['concept', 'Concept'], ['definitief', 'Definitief']],
        extra: [],
        nextPage: null,
        loadingMore: false,
        // Filter of zoekterm: eigen cache per weergave, de standaardweergave heet gewoon 'begrotingen'.
        filter() {
            const params = new URLSearchParams();
            if (this.status) params.set('status', this.status);
            if (this.q.trim()) params.set('q', this.q.trim());
            const query = params.toString();
            this.extra = [];
            this.nextPage = null;
            return this.show(query ? `begrotingen:${query}` : 'begrotingen', query ? `begrotingen?${query}` : 'begrotingen');
        },
        get rows() {
            const seen = new Set((this.data?.budgets ?? []).map((budget) => budget.id));
            return [...(this.data?.budgets ?? []), ...this.extra.filter((budget) => !seen.has(budget.id))];
        },
        get hasMore() {
            return this.nextPage !== null ? this.nextPage > 0 : Boolean(this.data?.more);
        },
        async loadMore() {
            if (this.loadingMore || !this.data) return;
            this.loadingMore = true;
            const page = this.nextPage || (this.data.page ?? 1) + 1;
            const separator = this.path.includes('?') ? '&' : '?';
            try {
                const result = await api(`${this.path}${separator}pagina=${page}`);
                this.extra.push(...result.budgets);
                this.nextPage = result.more ? page + 1 : 0;
            } catch (error) {
                Alpine.store('os').toast(errorText(error, 'Meer laden lukte niet. Controleer de verbinding.'));
            }
            this.loadingMore = false;
        },
    }));

    Alpine.data('budget', (id) => ({
        ...screen(`begroting:${id}`, `begrotingen/${id}`),
        busy: null,
        // Open hoofdstukken (id => true/false); standaard open als er regels in staan, zoals op de website.
        open: {},
        chapterCode: '',
        paragraphName: {},
        newLine: {},
        newMarkup: { name: '', percentage: '0' },
        newOption: { description: '', quantity: '1', unit: 'pst', price: '' },
        newTerm: { description: '', percentage: '0' },
        // Regel bewerken in een sheet: { id, label, ...velden }.
        editing: null,
        // Uit de prijslijst zoeken voor een paragraaf: { paragraph, label, term, results, searching }.
        picker: null,
        // Uitgangspunten bewerken: { docs, incl, excl } als tekst, een regel per punt.
        scopeForm: null,
        scopeBlocks: [
            ['docs', 'Op basis van', 'De tekeningen en stukken waarop deze begroting rust'],
            ['incl', 'Inbegrepen', 'Wat er zeker in zit'],
            ['excl', 'Niet inbegrepen', 'Wat de klant hier niet voor krijgt'],
        ],
        isOpen(chapter) {
            return this.open[chapter.id] ?? chapter.lines > 0;
        },
        toggle(chapter) {
            this.open[chapter.id] = !this.isOpen(chapter);
        },
        // Eén wijziging naar de server; het antwoord is de hele begroting, die komt meteen in beeld en in de cache.
        async run(key, path, { method = 'POST', body, done } = {}) {
            if (this.busy) return false;
            this.busy = key;
            try {
                const fresh = await api(path, { method, body });
                cache.write(this.cacheName, fresh, JSON.stringify(fresh));
                this.present(cache.read(this.cacheName));
                fetchedAt.begrotingen = 0;
                if (done) Alpine.store('os').toast(done);
                return true;
            } catch (error) {
                Alpine.store('os').toast(errorText(error, 'Opslaan lukte niet. Controleer de verbinding.'));
                this.revert();
                return false;
            } finally {
                this.busy = null;
            }
        },

        // Wat er nu in een veld staat, klopt niet met de server: de velden terug op de bewaarde waarden.
        revert() {
            const entry = cache.read(this.cacheName);
            if (entry) this.data = JSON.parse(entry.json);
        },
        // Een veld dat op de website bij verlaten opslaat (staart, termijnen): leeg getal = 0, lege naam = terug.
        fieldValue(field, value) {
            const text = String(value ?? '').trim();
            if (text) return text;
            return ['name', 'description'].includes(field) ? null : '0';
        },

        // Kop
        finalise() {
            return this.run('finalise', `begrotingen/${id}/definitief`, { done: 'Begroting staat op definitief. De regels zijn nu op slot.' });
        },
        async reopen() {
            if (await this.run('reopen', `begrotingen/${id}/heropenen`)) Alpine.store('os').toast(`Begroting heropend als versie ${this.data.version}.`);
        },

        // Hoofdstukken en paragrafen
        async addChapter() {
            const code = this.chapterCode || this.data?.chapter_library[0]?.code;
            if (!code) return;
            if (await this.run('chapter', `begrotingen/${id}/hoofdstukken`, { body: { code }, done: `Hoofdstuk ${code} toegevoegd.` })) this.chapterCode = '';
        },
        async removeChapter(chapter) {
            if (!(await Alpine.store('os').ask(`Hoofdstuk ${chapter.code} ${chapter.name} verwijderen, met alle regels erin?`, 'Verwijderen'))) return;
            this.run(`chapter:${chapter.id}`, `begrotingshoofdstukken/${chapter.id}`, { method: 'DELETE', done: `Hoofdstuk ${chapter.code} verwijderd.` });
        },
        async addParagraph(chapter) {
            const name = (this.paragraphName[chapter.id] ?? '').trim();
            if (!name) return;
            if (await this.run(`paragraph:${chapter.id}`, `begrotingshoofdstukken/${chapter.id}/paragrafen`, { body: { name }, done: 'Paragraaf toegevoegd.' })) {
                this.paragraphName[chapter.id] = '';
            }
        },

        // Regels
        lineForm(paragraph) {
            this.newLine[paragraph.id] ??= { description: '', quantity: '1', unit: 'st' };
            return this.newLine[paragraph.id];
        },
        async addLine(paragraph) {
            const form = this.lineForm(paragraph);
            if (!form.description.trim()) return;
            if (await this.run(`line:${paragraph.id}`, `begrotingsparagrafen/${paragraph.id}/regels`, { body: { ...form, description: form.description.trim() }, done: 'Regel toegevoegd.' })) {
                this.newLine[paragraph.id] = { description: '', quantity: '1', unit: 'st' };
            }
        },
        edit(line, paragraph) {
            if (!this.data?.draft || line.text) return;
            this.editing = {
                id: line.id,
                label: paragraph.label,
                source: line.source,
                description: line.description,
                unit: line.unit ?? '',
                ...Object.fromEntries(LINE_NUMBERS.map((field) => [field, line[field] ?? ''])),
            };
        },
        async saveLine() {
            const form = this.editing;
            if (!form || !form.description.trim()) return;
            const body = { description: form.description.trim(), unit: form.unit.trim() || 'st' };
            LINE_NUMBERS.forEach((field) => { body[field] = String(form[field] ?? '').trim() || '0'; });
            if (await this.run('saveline', `begrotingsregels/${form.id}`, { method: 'PATCH', body, done: 'Regel bijgewerkt.' })) this.editing = null;
        },
        async removeLine() {
            const form = this.editing;
            if (!form || !(await Alpine.store('os').ask('Deze regel verwijderen?', 'Verwijderen'))) return;
            if (await this.run('removeline', `begrotingsregels/${form.id}`, { method: 'DELETE', done: 'Regel verwijderd.' })) this.editing = null;
        },

        // Uit de prijslijst (api.app.price-items.search, dezelfde zoekfunctie als de website)
        openPicker(paragraph) {
            this.picker = { paragraph: paragraph.id, label: paragraph.label, term: '', results: [], searching: false, searched: '' };
            this.$nextTick(() => this.$refs.pickerInput?.focus());
        },
        async search() {
            const picker = this.picker;
            if (!picker) return;
            const term = picker.term.trim();
            if (term.length < 2) {
                picker.results = [];
                picker.searched = '';
                return;
            }
            picker.searching = true;
            try {
                const { items } = await api(`prijsitems?q=${encodeURIComponent(term)}`);
                if (this.picker === picker && picker.term.trim() === term) {
                    picker.results = items ?? [];
                    picker.searched = term;
                }
            } catch (error) {
                Alpine.store('os').toast(errorText(error, 'Zoeken in de prijslijst lukte niet. Controleer de verbinding.'));
            }
            picker.searching = false;
        },
        async pick(item) {
            const picker = this.picker;
            if (!picker) return;
            const body = { price_item_id: item.id, description: item.name, unit: item.unit, quantity: '1' };
            if (await this.run(`pick:${item.id}`, `begrotingsparagrafen/${picker.paragraph}/regels`, { body, done: `${item.name} toegevoegd.` })) this.picker = null;
        },

        // Staart
        updateMarkup(markup, field, value) {
            const text = this.fieldValue(field, value);
            if (text === null) return this.revert();
            if (text === String(markup[field])) return;
            this.run(`markup:${markup.id}`, `begrotingsstaart/${markup.id}`, { method: 'PATCH', body: { [field]: text } });
        },
        removeMarkup(markup) {
            this.run(`markup:${markup.id}`, `begrotingsstaart/${markup.id}`, { method: 'DELETE', done: 'Staartpost verwijderd.' });
        },
        async addMarkup() {
            const form = this.newMarkup;
            if (!form.name.trim()) return;
            if (await this.run('newmarkup', `begrotingen/${id}/staart`, { body: { name: form.name.trim(), percentage: String(form.percentage).trim() || '0' }, done: 'Staartpost toegevoegd.' })) {
                this.newMarkup = { name: '', percentage: '0' };
            }
        },

        // Opties en meerwerk
        priceOption(option, value) {
            if (String(value).trim() === option.price) return;
            this.run(`option:${option.id}`, `begrotingsopties/${option.id}`, { method: 'PATCH', body: { price: String(value).trim() || '0' } });
        },
        decideOption(option, action) {
            this.run(`option:${option.id}`, `begrotingsopties/${option.id}`, { method: 'PATCH', body: { status: action.value }, done: 'Optie bijgewerkt.' });
        },
        async removeOption(option) {
            if (!(await Alpine.store('os').ask(`Optie "${option.description}" verwijderen?`, 'Verwijderen'))) return;
            this.run(`option:${option.id}`, `begrotingsopties/${option.id}`, { method: 'DELETE', done: 'Optie verwijderd.' });
        },
        async addOption() {
            const form = this.newOption;
            if (!form.description.trim()) return;
            if (await this.run('newoption', `begrotingen/${id}/opties`, { body: { ...form, description: form.description.trim() }, done: 'Optie toegevoegd.' })) {
                this.newOption = { description: '', quantity: '1', unit: 'pst', price: '' };
            }
        },

        // Betaalschema
        updateTerm(term, field, value) {
            const text = this.fieldValue(field, value);
            if (text === null) return this.revert();
            if (text === String(term[field])) return;
            this.run(`term:${term.id}`, `begrotingstermijnen/${term.id}`, { method: 'PATCH', body: { [field]: text } });
        },
        removeTerm(term) {
            this.run(`term:${term.id}`, `begrotingstermijnen/${term.id}`, { method: 'DELETE', done: 'Termijn verwijderd.' });
        },
        async addTerm() {
            const form = this.newTerm;
            if (!form.description.trim()) return;
            if (await this.run('newterm', `begrotingen/${id}/termijnen`, { body: { description: form.description.trim(), percentage: String(form.percentage).trim() || '0' }, done: 'Termijn toegevoegd.' })) {
                this.newTerm = { description: '', percentage: '0' };
            }
        },

        // Uitgangspunten
        editScope() {
            const scope = this.data.scope;
            this.scopeForm = { docs: scope.docs.join('\n'), incl: scope.incl.join('\n'), excl: scope.excl.join('\n') };
        },
        async saveScope() {
            const form = this.scopeForm;
            if (!form) return;
            const body = { scope_docs: form.docs, scope_incl: form.incl, scope_excl: form.excl };
            if (await this.run('scope', `begrotingen/${id}`, { method: 'PATCH', body, done: 'Begroting bijgewerkt.' })) this.scopeForm = null;
        },
    }));
}
