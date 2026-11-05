// Sheets wegvegen: x-swipe-dismiss="sheet = null" op het paneel. Omlaag vegen sleept het paneel mee,
// ver genoeg of snel genoeg sluit het. Staat de inhoud niet bovenaan, dan scrollt die gewoon. Met
// touch-events, want alleen die kunnen het scrollen van de browser tegenhouden. Gedeeld door de website
// (app.js) en de schermen in de app (mobile/app/main.js): Alpine.plugin(swipeDismiss).
export default (Alpine) => Alpine.directive('swipe-dismiss', (el, { expression }, { evaluateLater, cleanup }) => {
    const START = 6;
    const CLOSE_AT = 0.3;
    const FLICK = 0.5;
    const dismiss = evaluateLater(expression);
    let gesture = null;
    let color = null;

    const backdrop = () => (el.parentElement.matches('[data-popup]') ? el.parentElement : null);

    // Op desktop staan de builderpanelen vast in de pagina en staat Nova midden in beeld.
    const isSheet = () => el.getBoundingClientRect().bottom >= window.innerHeight - 1
        && (backdrop() !== null || getComputedStyle(el).position === 'fixed');

    const isScrolled = (target) => {
        for (let node = target; node; node = node === el ? null : node.parentElement) {
            if (node.scrollTop > 0) return true;
        }
        return false;
    };

    const shade = (progress) => {
        if (backdrop()) backdrop().style.backgroundColor = `color-mix(in srgb, ${color} ${(1 - progress) * 100}%, transparent)`;
    };

    const settle = (close) => {
        const motion = 'motion' in document.documentElement.dataset && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const duration = motion ? 220 : 0;
        const from = el.style.transform;
        el.style.transform = '';
        const animation = el.animate(
            [{ transform: from }, { transform: close ? 'translateY(100%)' : 'translateY(0)' }],
            { duration, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)', fill: 'forwards' },
        );
        if (backdrop()) backdrop().style.transition = `background-color ${duration}ms`;
        shade(close ? 1 : 0);

        animation.finished.then(() => {
            if (close) dismiss();
            Alpine.nextTick(() => {
                animation.cancel();
                backdrop()?.style.removeProperty('background-color');
                backdrop()?.style.removeProperty('transition');
            });
        });
    };

    const start = (event) => {
        gesture = null;
        if (event.touches.length !== 1 || !isSheet()) return;
        if (event.target.closest('input, textarea, [contenteditable="true"]') || isScrolled(event.target)) return;
        const { clientX, clientY } = event.touches[0];
        gesture = { startX: clientX, startY: clientY, lastY: clientY, lastTime: event.timeStamp, speed: 0, offset: 0, dragging: false };
    };

    const move = (event) => {
        if (!gesture) return;
        const { clientX, clientY } = event.touches[0];
        const moveX = clientX - gesture.startX;
        const moveY = clientY - gesture.startY;

        if (!gesture.dragging) {
            if (Math.hypot(moveX, moveY) < START) return;
            if (moveY <= Math.abs(moveX) || !event.cancelable) {
                gesture = null;
                return;
            }
            gesture.dragging = true;
            gesture.height = el.offsetHeight;
            backdrop()?.style.removeProperty('background-color');
            color = backdrop() ? getComputedStyle(backdrop()).backgroundColor : null;
            backdrop()?.style.setProperty('transition', 'none');
        }

        event.preventDefault();
        gesture.speed = (clientY - gesture.lastY) / Math.max(1, event.timeStamp - gesture.lastTime);
        gesture.lastY = clientY;
        gesture.lastTime = event.timeStamp;
        gesture.offset = Math.max(0, moveY);
        el.style.transform = `translateY(${gesture.offset}px)`;
        shade(gesture.offset / gesture.height);
    };

    const end = (event) => {
        if (!gesture?.dragging) {
            gesture = null;
            return;
        }
        const flicked = gesture.speed > FLICK && event.timeStamp - gesture.lastTime < 100;
        const close = event.type === 'touchend' && (flicked || gesture.offset > gesture.height * CLOSE_AT);
        gesture = null;
        settle(close);
    };

    el.addEventListener('touchstart', start, { passive: true });
    el.addEventListener('touchmove', move, { passive: false });
    el.addEventListener('touchend', end);
    el.addEventListener('touchcancel', end);

    cleanup(() => {
        el.removeEventListener('touchstart', start);
        el.removeEventListener('touchmove', move);
        el.removeEventListener('touchend', end);
        el.removeEventListener('touchcancel', end);
    });
});
