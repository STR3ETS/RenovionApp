// Klantportaal zoals de klant het ziet (portal-preview/index en show), van binnenuit: lopende projecten met
// portaalaccount, wat op de klant wacht en hoeveel foto's hij ziet, en per project de voortgang, de actie van
// de klant, zijn foto's, wat hij aftekende, deze week en service. Alleen lezen, alleen kantoor.
// Gegevens: api/app/klantportaal (Api\App\PortalPreviewController). Template: klantportaal.html.

const API = import.meta.env.VITE_RENOVION_API;

export const routes = {
    screens: ['klantportaal'],
    details: { portaalproject: /^\/klantportaal\/(\d+)$/ },
    parents: { portaalproject: 'klantportaal' },
    sections: { klantportaal: 'verkoop', portaalproject: 'verkoop' },
    paths: { '/klantportaal': 'klantportaal' },
};

export default function register({ Alpine, screen }) {
    Alpine.data('portalPreview', () => ({ ...screen('klantportaal', 'klantportaal') }));

    Alpine.data('portalPreviewProject', (id) => ({
        ...screen(`portaalproject:${id}`, `klantportaal/${id}`),
        viewer: null, // de foto die groot open staat
        // Ondertekend adres van de app (api/app/fotos/{id}): <img> kan geen token meesturen.
        photoUrl: (photo) => `${API}${photo.url}`,
    }));
}
