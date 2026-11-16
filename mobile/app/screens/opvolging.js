// Opvolging: alle open aanvragen, te laat bovenaan, dan wat niets gepland heeft (follow-ups/index).
// Alles in één keer; het filter op staat gebeurt op de telefoon (ook zonder verbinding). Een rij opent #/aanvraag/{id}.
// Gegevens: api/app/opvolging (Api\App\FollowUpController). Template: opvolging.html.

export const routes = {
    screens: ['opvolging'],
    details: {},
    parents: { opvolging: 'aanvragen' },
    sections: { opvolging: 'verkoop' },
    paths: { '/opvolging': 'opvolging' },
};

export default function register({ Alpine, screen }) {
    Alpine.data('followUps', () => ({
        ...screen('opvolging', 'opvolging'),
        filter: '',
        get rows() {
            const rows = this.data?.rows ?? [];
            return this.filter ? rows.filter((row) => row.key === this.filter) : rows;
        },
        // Zoals de website: een tab zonder aanvragen alleen als hij gekozen is.
        get filters() {
            return (this.data?.filters ?? []).filter((option) => option.count > 0 || option.value === this.filter);
        },
    }));
}
