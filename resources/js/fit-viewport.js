// x-fit-viewport: een schermvullende laag (Nova) precies over het zichtbare deel van het scherm, ook met het
// toetsenbord open. Android maakt de webview kleiner; iOS legt het toetsenbord eroverheen en schuift het
// zichtbare deel omhoog (visualViewport.offsetTop). Zonder dit schuift op iOS de kop uit beeld en staat de
// invoerbalk niet tegen het toetsenbord. Na een andere hoogte volgt een "fitted"-event (bijv. naar het nieuwste
// bericht scrollen). Gedeeld door de website/serverpagina's (app.js) en de app (mobile/app/main.js), zoals
// fit() van het chatgesprek in main.js.
export default function fitViewport(Alpine) {
    Alpine.directive('fit-viewport', (el, directive, { cleanup }) => {
        const viewport = window.visualViewport;
        if (!viewport) return;

        let height = 0;
        const fit = () => {
            el.style.height = `${viewport.height}px`;
            el.style.transform = viewport.offsetTop > 0 ? `translateY(${viewport.offsetTop}px)` : '';
            if (viewport.height !== height) {
                height = viewport.height;
                el.dispatchEvent(new CustomEvent('fitted'));
            }
        };

        viewport.addEventListener('resize', fit);
        viewport.addEventListener('scroll', fit);
        fit();
        cleanup(() => {
            viewport.removeEventListener('resize', fit);
            viewport.removeEventListener('scroll', fit);
        });
    });
}
