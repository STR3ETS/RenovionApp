{{-- Pagina-opbouw bij een paginawissel: zet de blokken klaar en haalt daarna de verberging uit
     partials/motion-head weg, in één keer, zodat er geen frame tussen zit. --}}
<script id="motion-stage">
    (() => {
        const root = document.documentElement;
        const main = document.querySelector('main');

        if (!root.classList.contains('enter-pending')) return;

        const MAX_BLOCKS = 28;
        const SKIP = ['SCRIPT', 'STYLE', 'TEMPLATE'];
        const CARD = '[data-enter], section, article, a, form, table, h1, h2, p, nav, header, ul, ol';
        const blocks = [];

        const collect = (parent) => {
            for (const element of parent.children) {
                if (SKIP.includes(element.tagName) || element.hasAttribute('data-no-enter')) continue;
                const isCard = element.matches(CARD) || /rounded|border|shadow|bg-/.test(element.className);
                if (element.matches('[data-fan]')) collect(element);
                else if (isCard || element.children.length === 0) blocks.push(element);
                else collect(element);
            }
        };
        collect(main);

        // Kaart zoomt open: de kop van het dossier groeit vanaf de aangetikte projectkaart.
        let zoomTarget = null;
        try {
            if (sessionStorage.getItem('renovion-zoom')) {
                zoomTarget = main.querySelector('[data-zoom-target]');
                if (zoomTarget) zoomTarget.style.viewTransitionName = 'project-zoom';
            }
            sessionStorage.removeItem('renovion-zoom');
        } catch { /* geen opslag: dan zonder zoom */ }

        const visible = blocks
            .filter((element) => element !== zoomTarget)
            .filter((element) => {
                const rect = element.getBoundingClientRect();
                return rect.height > 0 && rect.top < innerHeight;
            })
            .slice(0, MAX_BLOCKS);

        visible.forEach((element, index) => {
            element.style.setProperty('--enter-index', index);

            // Waaier: per positie vanaf het midden van de rij licht gedraaid binnenkomen.
            const fan = element.parentElement.closest('[data-fan]');
            if (fan === element.parentElement) {
                const columns = getComputedStyle(fan).gridTemplateColumns.split(' ').length;

                @nativeapp
                    // App: gemeten op het scherm. Een kaart over de volle breedte blijft zo recht en links en
                    // rechts draaien de goede kant op, ook als een eerdere kaart de hele rij vult.
                    const row = fan.getBoundingClientRect();
                    const card = element.getBoundingClientRect();
                    const offset = (card.left + card.width / 2 - row.left - row.width / 2) / (row.width / columns);
                    element.style.setProperty('--enter-rotate', `${offset * 2.4}deg`);
                    element.style.setProperty('--enter-y', `${28 + Math.abs(offset) * 10}px`);
                @else
                    const position = [...fan.children].indexOf(element) % columns;
                    element.style.setProperty('--enter-rotate', `${(position - (columns - 1) / 2) * 2.4}deg`);
                @endnativeapp
            }

            element.classList.add('enter-block');
        });

        main.querySelectorAll('.h-full[style*="width"]').forEach((bar) => {
            if (bar.getBoundingClientRect().top < innerHeight) bar.classList.add('enter-bar');
        });

        main.addEventListener('animationend', ({ target }) => target.classList.remove('enter-block', 'enter-bar'));

        // Cijfers staan vóór de eerste paint op 0; motion.js telt ze daarna op. Het vangnet zet de echte
        // waarde terug als dat script niet laadt.
        const counters = [...main.querySelectorAll('[data-count]')].filter((element) => element.getBoundingClientRect().top < innerHeight);
        counters.forEach((element) => {
            const value = element.textContent.trim();
            element.dataset.countTo = value;
            element.textContent = value.replace(/\d[\d.]*(?:,(\d+))?/, (match, decimals) => (decimals ? `0,${'0'.repeat(decimals.length)}` : '0'));
        });
        setTimeout(() => counters.forEach((element) => {
            if (element.dataset.countTo) element.textContent = element.dataset.countTo;
        }), 6000);

        root.classList.add('motion-enter');
        root.classList.remove('enter-pending');
    })();
</script>
