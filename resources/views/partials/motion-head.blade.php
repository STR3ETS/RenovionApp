{{-- Beslist vóór de eerste paint of de pagina-opbouw speelt. Bij een snelle (gecachete) pagina komt de
     browser anders al aan schilderen vóór partials/motion-stage aan het eind van <main> draait: dan staat
     alles er even en begint de animatie daarna opnieuw. enter-pending houdt <main> tot die tijd verborgen.
     rel=expect laat de browser wachten met schilderen tot de stage er is: zo blijft de vorige pagina staan
     in plaats van een lege, en heeft de kaart-zoom een kop om naartoe te groeien. Browsers zonder rel=expect
     vallen terug op de verberging. Terug, herladen en dezelfde pagina (opslaan, filteren, tabs) spelen niet. --}}
<link rel="expect" href="#motion-stage" blocking="render">
<style>html.enter-pending main { visibility: hidden; }</style>
<script>
    (() => {
        const root = document.documentElement;
        const type = performance.getEntriesByType('navigation')[0]?.type;
        const samePage = document.referrer && new URL(document.referrer).pathname === location.pathname;

        if (!('motion' in root.dataset) || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        if (type === 'reload' || type === 'back_forward' || samePage) return;

        root.classList.add('enter-pending');
        document.addEventListener('DOMContentLoaded', () => root.classList.remove('enter-pending'));

        // Wachten tot de opbouw te zien is: bij het starten van de app ligt de splash er nog overheen
        // (native.js haalt enter-paused weg), en vooraf geladen pagina's staan nog niet in beeld.
        const pause = () => root.classList.add('enter-paused');
        const resume = () => root.classList.remove('enter-paused');

        if (window.Capacitor?.isNativePlatform?.()) {
            try {
                if (!sessionStorage.getItem('renovion-started')) pause();
                sessionStorage.setItem('renovion-started', '1');
            } catch { /* geen opslag: dan niet wachten */ }
            setTimeout(resume, 4000); // vangnet als native.js niet laadt
        }

        if (document.prerendering) {
            pause();
            document.addEventListener('prerenderingchange', resume, { once: true });
        }
    })();
</script>
