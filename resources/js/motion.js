// Beweging: optellende cijfers, schuivende pil en de kaart die open zoomt. De pagina-opbouw zelf staat
// in partials/motion-stage (die moet vóór de eerste paint klaar zijn). Alleen transform en opacity, en
// niets bij gewone updates: cijfers tellen alleen bij een paginawissel.
const root = document.documentElement;
const isEnabled = () => 'motion' in root.dataset && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const COUNT_DURATION = 900;
const COUNT_STAGGER = 70;
const PILL_DURATION = 340;

// Telt alleen het getal in de tekst op, zodat €, punten en decimalen blijven staan.
function countUp(element, delay) {
    const original = element.dataset.countTo;
    const match = original?.match(/\d[\d.]*(?:,\d+)?/);
    delete element.dataset.countTo;
    if (!match) return;

    const target = Number(match[0].replace(/\./g, '').replace(',', '.'));
    const decimals = (match[0].split(',')[1] ?? '').length;
    const format = new Intl.NumberFormat('nl-NL', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    const render = (value) => {
        element.textContent = original.replace(match[0], format.format(value));
    };

    render(0);
    const start = performance.now() + delay;
    const tick = (now) => {
        const progress = Math.min(1, Math.max(0, (now - start) / COUNT_DURATION));
        render(target * (1 - (1 - progress) ** 3));
        if (progress < 1) requestAnimationFrame(tick);
        else element.textContent = original;
    };
    requestAnimationFrame(tick);
}

function startCounters() {
    document.querySelectorAll('main [data-count-to]').forEach((element, index) => countUp(element, index * COUNT_STAGGER));
}

// Cijfers wachten op hetzelfde moment als de blokken (splash weg, pagina in beeld).
function whenReleased(callback) {
    if (!root.classList.contains('enter-paused')) {
        callback();
        return;
    }

    const observer = new MutationObserver(() => {
        if (root.classList.contains('enter-paused')) return;
        observer.disconnect();
        callback();
    });
    observer.observe(root, { attributes: true, attributeFilter: ['class'] });
}

if (root.classList.contains('motion-enter')) whenReleased(startCounters);

// Schuivende pil: tijdelijk blokje dat van de oude naar de nieuwe keuze schuift.
const isSelected = (element) => /\bbg-navy-9[05]0\b/.test(element.className);

function slidePill(group, from) {
    const to = [...group.children].find(isSelected);
    if (!to || !from.element.isConnected || to === from.element) return;

    const box = to.getBoundingClientRect();
    if (!box.width) return;

    // De knoppen hebben zelf een kleurovergang: die moet uit, anders knippert de nieuwe keuze aan het eind.
    to.style.transition = 'none';
    from.element.style.transition = 'none';
    const color = getComputedStyle(to).backgroundColor;

    const pill = document.createElement('span');
    pill.setAttribute('aria-hidden', 'true');
    Object.assign(pill.style, {
        position: 'absolute',
        zIndex: -1,
        left: `${to.offsetLeft}px`,
        top: `${to.offsetTop}px`,
        width: `${box.width}px`,
        height: `${box.height}px`,
        borderRadius: getComputedStyle(to).borderRadius,
        backgroundColor: color,
        transformOrigin: 'top left',
    });
    group.append(pill);
    to.style.backgroundColor = 'transparent';

    const moved = `translate(${from.left - box.left}px, ${from.top - box.top}px) scale(${from.width / box.width}, ${from.height / box.height})`;
    pill.animate([{ transform: moved }, { transform: 'none' }], { duration: PILL_DURATION, easing: 'cubic-bezier(0.3, 0.7, 0.2, 1)' })
        .finished.finally(() => {
            to.style.backgroundColor = '';
            pill.remove();
            void to.offsetWidth; // eerst de kleur toepassen, dan pas de overgang terug
            to.style.transition = '';
            from.element.style.transition = '';
        });
}

document.addEventListener('click', (event) => {
    if (!isEnabled()) return;

    // Voor Alpine meten: in de capture-fase staat de oude keuze nog op zijn plek.
    const group = event.target.closest?.('[data-pill]');
    const button = event.target.closest?.('button');
    if (group && button?.parentElement === group) {
        const selected = [...group.children].find(isSelected);
        if (selected && selected !== button) {
            const { left, top, width, height } = selected.getBoundingClientRect();
            const from = { element: selected, left, top, width, height };
            requestAnimationFrame(() => slidePill(group, from));
        }
    }

    // Kaart zoomt open: de nieuwe pagina pakt dit op in partials/motion-stage.
    const card = event.target.closest?.('a[data-zoom-card]');
    if (card && document.startViewTransition !== undefined) {
        try {
            sessionStorage.setItem('renovion-zoom', '1');
            card.style.viewTransitionName = 'project-zoom';
        } catch { /* geen opslag: dan zonder zoom */ }
    }
}, true);

// Terug naar deze pagina: de kaart mag niet nog steeds de zoomnaam dragen.
window.addEventListener('pageshow', () => document.querySelectorAll('a[data-zoom-card]').forEach((card) => {
    card.style.viewTransitionName = '';
}));

// Instellingen: direct effect, zonder de pagina te herladen.
window.setMotion = (enabled) => root.toggleAttribute('data-motion', enabled);
