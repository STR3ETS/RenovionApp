// Foto schermvullend bekijken: tik op een [data-photo] (met data-caption en data-meta) en dit scherm opent.
// Vegen = volgende foto, knijpen of dubbeltikken = zoomen, omlaag vegen = sluiten. Gedeeld door de website
// (app.js, components/photo-viewer) en de app (mobile/app/main.js, het blok "Foto schermvullend" in index.html).
export default function photoViewer(Alpine) {
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest?.('[data-photo]');
        if (!trigger) return;
        event.preventDefault();
        window.dispatchEvent(new CustomEvent('photo-open', { detail: trigger }));
    });

    Alpine.data('photoViewer', () => {
        const SWIPE = 60;
        const MAX_ZOOM = 5;
        const pointers = new Map();
        let pinch = null;
        let drag = null;
        let lastTap = 0;

        const distance = () => {
            const [a, b] = [...pointers.values()];
            return Math.hypot(a.x - b.x, a.y - b.y);
        };

        return {
            isOpen: false,
            items: [],
            index: 0,
            scale: 1,
            x: 0,
            y: 0,

            get item() {
                return this.items[this.index] ?? {};
            },

            get transform() {
                return `translate(${this.x}px, ${this.y}px) scale(${this.scale})`;
            },

            // Alleen wat nu zichtbaar is, zodat het fase-filter en verborgen tabs meetellen.
            show(trigger) {
                const seen = new Set();
                this.items = [...document.querySelectorAll('[data-photo]')]
                    .filter((el) => el === trigger || el.offsetParent !== null)
                    .filter((el) => !seen.has(el.dataset.photo) && seen.add(el.dataset.photo))
                    .map((el) => ({ src: el.dataset.photo, caption: el.dataset.caption, meta: el.dataset.meta }));
                this.index = Math.max(0, this.items.findIndex((item) => item.src === trigger.dataset.photo));
                this.reset();
                this.isOpen = true;
                document.documentElement.classList.add('overflow-hidden');
                this.$nextTick(() => window.dispatchEvent(new Event('renovion-top-changed')));
            },

            close() {
                this.isOpen = false;
                document.documentElement.classList.remove('overflow-hidden');
                setTimeout(() => window.dispatchEvent(new Event('renovion-top-changed')), 200); // na de fade-out
            },

            go(step) {
                const next = this.index + step;
                if (next < 0 || next >= this.items.length) return;
                this.index = next;
                this.reset();
            },

            reset() {
                this.scale = 1;
                this.x = 0;
                this.y = 0;
                this.cancel();
            },

            cancel() {
                pointers.clear();
                pinch = null;
                drag = null;
            },

            down(event) {
                event.currentTarget.setPointerCapture(event.pointerId);
                pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
                if (pointers.size === 2) {
                    pinch = { distance: distance(), scale: this.scale };
                    drag = null;
                } else if (pointers.size === 1) {
                    drag = { startX: event.clientX, startY: event.clientY, x: this.x, y: this.y };
                }
            },

            move(event) {
                if (!pointers.has(event.pointerId)) return;
                pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
                if (pinch && pointers.size === 2) {
                    this.scale = Math.min(MAX_ZOOM, Math.max(1, pinch.scale * (distance() / pinch.distance)));
                } else if (drag && this.scale > 1) {
                    this.x = drag.x + event.clientX - drag.startX;
                    this.y = drag.y + event.clientY - drag.startY;
                }
            },

            up(event) {
                pointers.delete(event.pointerId);
                if (pinch) {
                    if (pointers.size === 0) pinch = null;
                    if (this.scale === 1) { this.x = 0; this.y = 0; }
                    return;
                }
                if (!drag) return;

                const moveX = event.clientX - drag.startX;
                const moveY = event.clientY - drag.startY;
                drag = null;

                if (Math.hypot(moveX, moveY) < 10) {
                    const now = Date.now();
                    if (now - lastTap < 300) {
                        this.scale = this.scale > 1 ? 1 : 2.5;
                        this.x = 0;
                        this.y = 0;
                    }
                    lastTap = now;
                } else if (this.scale === 1) {
                    if (Math.abs(moveX) > SWIPE && Math.abs(moveX) > Math.abs(moveY)) this.go(moveX < 0 ? 1 : -1);
                    else if (moveY > SWIPE * 2) this.close();
                }
            },
        };
    });
}
