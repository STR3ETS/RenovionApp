// De UX-laag uit de briefing: sneltoetsen, een voortgangsbalk bij navigatie en
// snelkoppelingen naar de schermen die je zelf het meest gebruikt.

const USAGE_KEY = 'renovion-schermgebruik';

function navData() {
    try {
        return JSON.parse(document.getElementById('nav-schermen')?.textContent || 'null') || { screens: [], go: {} };
    } catch (e) {
        return { screens: [], go: {} };
    }
}

// In een invoerveld is een letter gewoon een letter.
function isTyping(target) {
    return target instanceof HTMLElement
        && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
}

/**
 * Sneltoetsen: Ctrl of Cmd met K zoekt, N opent Nieuw, G gevolgd door een
 * letter springt naar een scherm, en het vraagteken toont de lijst.
 */
function shortcuts(nav) {
    const help = document.getElementById('sneltoetsen');
    let waitingForScreen = false;
    let timer = null;

    document.addEventListener('keydown', (event) => {
        const key = event.key.toLowerCase();

        if ((event.ctrlKey || event.metaKey) && key === 'k') {
            const search = document.getElementById('globaal-zoeken');

            if (search) {
                event.preventDefault();
                search.focus();
                search.select();
            }

            return;
        }

        if (event.ctrlKey || event.metaKey || event.altKey || isTyping(event.target)) return;

        if (waitingForScreen) {
            waitingForScreen = false;
            clearTimeout(timer);

            if (nav.go[key]) {
                event.preventDefault();
                window.location.href = nav.go[key];
            }

            return;
        }

        if (event.key === '?' && help) {
            event.preventDefault();
            help.open ? help.close() : help.showModal();
        } else if (key === 'n') {
            const button = document.querySelector('[data-sneltoets-nieuw]');

            if (button) {
                event.preventDefault();
                button.click();
            }
        } else if (key === 'g') {
            // Even wachten op de tweede toets; daarna is G weer gewoon G.
            waitingForScreen = true;
            timer = setTimeout(() => { waitingForScreen = false; }, 1500);
        }
    });

    help?.addEventListener('click', (event) => {
        if (event.target === help) help.close();
    });
}

/**
 * Binnen 400 milliseconden moet je zien dat er iets gebeurt. Duurt een
 * paginawissel langer dan een oogwenk, dan loopt er een balk bovenin.
 */
function navigationProgress() {
    const bar = document.getElementById('nav-voortgang');
    if (! bar) return;

    let timer = null;

    const start = () => {
        clearTimeout(timer);
        timer = setTimeout(() => bar.classList.add('bezig'), 150);
    };

    const stop = () => {
        clearTimeout(timer);
        bar.classList.remove('bezig');
    };

    document.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;

        if (! link || event.defaultPrevented || event.button !== 0
            || event.ctrlKey || event.metaKey || event.shiftKey
            || link.target === '_blank' || link.hasAttribute('download')) return;

        const url = new URL(link.href, window.location.href);

        // Alleen een echte paginawissel binnen de app.
        if (url.origin !== window.location.origin || (url.pathname === window.location.pathname && url.hash)) return;

        start();
    });

    document.addEventListener('submit', (event) => {
        if (! event.defaultPrevented) start();
    });

    // Terug uit de geschiedenis: de pagina komt uit het geheugen en de balk moet weg.
    window.addEventListener('pageshow', stop);
}

/**
 * Tel welk scherm je opent en toon op Vandaag de vijf die je het meest
 * gebruikt. Het staat alleen in deze browser: het is een gemak, geen meting.
 */
function frequentScreens(nav) {
    let usage = {};

    try {
        usage = JSON.parse(localStorage.getItem(USAGE_KEY) || '{}') || {};
    } catch (e) {
        usage = {};
    }

    const current = nav.screens.find((screen) => screen.active);

    if (current && current.key !== 'vandaag') {
        usage[current.key] = (usage[current.key] || 0) + 1;

        try {
            localStorage.setItem(USAGE_KEY, JSON.stringify(usage));
        } catch (e) {
            // Zonder opslag blijft de lijst leeg; het scherm werkt gewoon.
        }
    }

    const holder = document.querySelector('[data-veelgebruikt]');
    if (! holder) return;

    const top = nav.screens
        .filter((screen) => screen.key !== 'vandaag' && (usage[screen.key] || 0) >= 2)
        .sort((a, b) => usage[b.key] - usage[a.key])
        .slice(0, 5);

    if (top.length === 0) return;

    const list = holder.querySelector('[data-veelgebruikt-lijst]');

    top.forEach((screen) => {
        const link = document.createElement('a');
        link.href = screen.url;
        link.textContent = screen.label;
        list.appendChild(link);
    });

    holder.hidden = false;
}

document.addEventListener('DOMContentLoaded', () => {
    const nav = navData();

    shortcuts(nav);
    navigationProgress();
    frequentScreens(nav);
});
