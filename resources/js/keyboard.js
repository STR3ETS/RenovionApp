// Toetsenbord open: zet .keyboard-open op <html>, zodat .hide-on-keyboard (onderbalk, Nova-knop) verdwijnt.
// Gedeeld door de website/serverpagina's (app.js) en de schermen in de app (mobile/app/main.js).
// Alleen touch, een tekstveld met focus én een schermhoogte die flink kleiner is geworden. Focus alleen is niet
// genoeg: de terugknop sluit het toetsenbord en de focus blijft staan.
const viewport = window.visualViewport;
if (viewport && window.matchMedia('(pointer: coarse)').matches) {
    const TEXT_FIELD = 'textarea, [contenteditable="true"], input:not([type="checkbox"], [type="radio"], [type="file"], [type="submit"], [type="button"], [type="date"], [type="time"], [type="range"], [type="color"])';
    let fullHeight = viewport.height;
    let width = viewport.width;

    const sync = () => {
        if (viewport.width !== width) { // gedraaid
            width = viewport.width;
            fullHeight = viewport.height;
        }
        fullHeight = Math.max(fullHeight, viewport.height);
        const open = fullHeight - viewport.height > 120 && Boolean(document.activeElement?.matches(TEXT_FIELD));
        document.documentElement.classList.toggle('keyboard-open', open);
    };

    viewport.addEventListener('resize', sync);
    document.addEventListener('focusin', sync);
    document.addEventListener('focusout', () => setTimeout(sync, 50));
}
