// Documenten: de bibliotheek (documents/index: zoeken, categorie, groepen met tellers; alleen kantoor) en één
// document (documents.show: wat het is, waar het bij hoort, openen of downloaden). Het bestand komt via een
// ondertekend adres; een foto staat meteen in beeld, een PDF of ander bestand opent in de webview.
// Gegevens: api/app/documenten* (Api\App\DocumentController). Template: documenten.html.
const API = import.meta.env.VITE_RENOVION_API;

export const routes = {
    screens: ['documenten'],
    details: { document: /^\/documenten\/(\d+)$/ },
    parents: { document: 'documenten' },
    sections: { documenten: 'organisatie', document: 'organisatie' },
    paths: { '/documenten': 'documenten' },
    // /documenten?groep=Technisch of ?categorie=factuur of ?q= opent hier met dat filter.
    query: ['documenten'],
};

export default function register({ Alpine, api, screen, errorText }) {
    const os = () => Alpine.store('os');

    Alpine.data('documents', () => {
        const start = os().query ?? {};
        const filters = { q: start.q ?? '', categorie: start.categorie ?? '', groep: start.groep ?? '' };
        const query = (values, page = 1) => {
            const params = new URLSearchParams(Object.entries({ ...values, page: page > 1 ? page : '' }).filter(([, value]) => value !== ''));
            return params.toString();
        };
        const base = screen(`documenten:${query(filters)}`, `documenten${query(filters) ? `?${query(filters)}` : ''}`);

        return {
            ...base,
            ...filters,
            extra: [],
            page: 1,
            more: null,
            loadingMore: false,
            timer: null,
            get list() {
                return [...(this.data?.documents ?? []), ...this.extra];
            },
            get hasMore() {
                return this.more ?? this.data?.more ?? false;
            },
            get values() {
                return { q: this.q.trim(), categorie: this.categorie, groep: this.groep };
            },
            destroy() {
                clearTimeout(this.timer);
            },
            // Ander filter: eerst wat er van die weergave bewaard is, dan vers (zoals de filters op Projecten).
            apply() {
                clearTimeout(this.timer);
                this.extra = [];
                this.page = 1;
                this.more = null;
                const qs = query(this.values);
                return this.show(`documenten:${qs}`, `documenten${qs ? `?${qs}` : ''}`);
            },
            search() {
                clearTimeout(this.timer);
                this.timer = setTimeout(() => this.apply(), 350);
            },
            setGroup(value) {
                this.groep = value;
                this.categorie = '';
                return this.apply();
            },
            async loadMore() {
                if (this.loadingMore) return;
                this.loadingMore = true;
                try {
                    const next = await api(`documenten?${query(this.values, this.page + 1)}`);
                    const known = new Set(this.list.map((document) => document.id));
                    this.extra = [...this.extra, ...next.documents.filter((document) => !known.has(document.id))];
                    this.page = next.page;
                    this.more = next.more;
                } catch (error) {
                    os().toast(errorText(error, 'Meer laden lukte niet. Controleer de verbinding.'));
                }
                this.loadingMore = false;
            },
        };
    });

    Alpine.data('documentFile', (id) => ({
        ...screen(`document:${id}`, `documenten/${id}`),
        viewer: false,
        fileUrl: (document) => `${API}${document.url}`,
        // Openen in de webview: iOS toont een PDF zelf (terug met vegen), een foto staat al in beeld.
        open(download = false) {
            if (!this.data) return;
            location.href = `${API}${download ? this.data.download_url : this.data.url}`;
        },
        back() {
            if (history.length > 1) history.back();
            else location.hash = os().crm ? '#/documenten' : '#/vandaag';
        },
    }));
}
