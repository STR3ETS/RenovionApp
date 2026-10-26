{{--
    Rich-text editor voor de offerte-builder (Eazyonline-concept): de x-rich
    directive (contenteditable die tweeweg synct met een Alpine-expressie),
    veilig plakken uit Word/HTML en een opmaakbalkje dat verschijnt zodra je
    tekst selecteert. Eén keer per pagina includen.
--}}
<style>
    .rich-edit { outline: none; cursor: text; min-height: 1.2em; }
    .rich-edit:empty:before { content: attr(data-ph); color: rgb(156 163 175); pointer-events: none; }
    .rich-edit:focus { background: rgba(248, 91, 11, 0.05); border-radius: 6px; box-shadow: 0 0 0 4px rgba(248, 91, 11, 0.05); }
    #fmtbar { position: fixed; z-index: 9998; display: none; gap: 2px; background: #070724; border-radius: 10px; padding: 3px; box-shadow: 0 6px 20px rgba(0, 0, 0, .3); }
    #fmtbar button { min-width: 30px; height: 28px; padding: 0 8px; border: none; background: transparent; border-radius: 7px; color: #fff; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; cursor: pointer; line-height: 1; }
    #fmtbar button:hover { background: rgba(248, 91, 11, .9); }
</style>

<script>
    document.addEventListener('alpine:init', () => {
        // Allowlist + sanering voor geplakte HTML (zelfde set als QuoteText::clean server-side).
        const RICH_ALLOWED = new Set(['P', 'BR', 'DIV', 'H1', 'H2', 'H3', 'H4', 'UL', 'OL', 'LI', 'STRONG', 'B', 'EM', 'I', 'U', 'S', 'SPAN', 'A']);

        const richCleanInto = (src, dst) => {
            src.childNodes.forEach((child) => {
                if (child.nodeType === 3) {
                    dst.appendChild(document.createTextNode(child.nodeValue));
                } else if (child.nodeType === 1) {
                    const tag = child.tagName;
                    if (RICH_ALLOWED.has(tag)) {
                        const clean = document.createElement(tag);
                        if (tag === 'SPAN') {
                            let style = '';
                            if (child.style && child.style.fontSize) style += 'font-size:' + child.style.fontSize + ';';
                            if (child.style && child.style.fontWeight && /^(bold|[6-9]00)$/.test(child.style.fontWeight)) style += 'font-weight:' + child.style.fontWeight + ';';
                            if (style) clean.setAttribute('style', style);
                        } else if (tag === 'A') {
                            const href = child.getAttribute('href') || '';
                            if (/^(https?:|mailto:|tel:|\/|#)/i.test(href)) clean.setAttribute('href', href);
                        }
                        richCleanInto(child, clean);
                        dst.appendChild(clean);
                    } else {
                        // Niet toegestaan (Word-rommel, script, img): tag weg, inhoud behouden.
                        richCleanInto(child, dst);
                    }
                }
            });
        };

        const richSanitizePaste = (html) => {
            const src = document.createElement('div');
            src.innerHTML = html;
            src.querySelectorAll('style,script,meta,link,title').forEach((node) => node.remove());
            const dst = document.createElement('div');
            richCleanInto(src, dst);
            return dst.innerHTML;
        };

        const escapeText = (text) => text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

        // Rich-text veld: contenteditable dat tweeweg synct met een Alpine-expressie.
        Alpine.directive('rich', (el, { expression }, { evaluateLater, evaluate, effect, cleanup }) => {
            el.setAttribute('contenteditable', 'true');
            el.classList.add('rich-edit');
            const getValue = evaluateLater(expression);

            const toHtml = (value) => {
                if (value === null || value === undefined) return '';
                value = String(value);
                if (/<[a-z!\/][\s\S]*>/i.test(value)) return value;
                return escapeText(value).replace(/\n/g, '<br>');
            };

            effect(() => {
                getValue((value) => {
                    const html = toHtml(value);
                    if (document.activeElement !== el && el.innerHTML !== html) el.innerHTML = html;
                });
            });

            const onInput = () => {
                try { evaluate(`${expression} = __richValue`, { scope: { __richValue: el.innerHTML } }); }
                catch (e) { console.error('rich-edit kon niet opslaan', e); }
            };

            // Plakken: HTML saneren (Word-rommel eruit, structuur en veilige opmaak blijven);
            // zonder HTML gewoon platte tekst.
            const onPaste = (event) => {
                event.preventDefault();
                const clipboard = event.clipboardData || window.clipboardData;
                const html = clipboard ? clipboard.getData('text/html') : '';
                const text = clipboard ? clipboard.getData('text/plain') : '';
                const clean = html ? richSanitizePaste(html) : escapeText(text).replace(/\n/g, '<br>');
                try { document.execCommand('insertHTML', false, clean); }
                catch (e) { document.execCommand('insertText', false, text); }
                onInput();
            };

            el.addEventListener('input', onInput);
            el.addEventListener('paste', onPaste);
            cleanup(() => {
                el.removeEventListener('input', onInput);
                el.removeEventListener('paste', onPaste);
            });
        });
    });
</script>

{{-- Opmaakbalkje: verschijnt als je tekst selecteert in een rich veld --}}
<div id="fmtbar">
    <button type="button" data-fmt="bold" title="Vet"><b>B</b></button>
    <button type="button" data-fmt="big" title="Groter">A+</button>
    <button type="button" data-fmt="small" title="Kleiner">A&minus;</button>
</div>
<script>
    (function () {
        const bar = document.getElementById('fmtbar');
        if (!bar) return;
        let editor = null;

        function activeRich() {
            const selection = window.getSelection();
            if (!selection || !selection.rangeCount) return null;
            let node = selection.anchorNode;
            while (node && node !== document) {
                if (node.nodeType === 1 && node.classList && node.classList.contains('rich-edit')) return node;
                node = node.parentNode;
            }
            return null;
        }

        function place() {
            const selection = window.getSelection();
            const rich = activeRich();
            if (!rich || !selection.rangeCount || selection.isCollapsed) { bar.style.display = 'none'; return; }
            editor = rich;
            const rect = selection.getRangeAt(0).getBoundingClientRect();
            bar.style.display = 'flex';
            let top = rect.top - bar.offsetHeight - 6;
            if (top < 6) top = rect.bottom + 6;
            bar.style.top = top + 'px';
            bar.style.left = Math.min(window.innerWidth - bar.offsetWidth - 8, Math.max(8, rect.left)) + 'px';
        }

        function wrapFontSize(factor) {
            const selection = window.getSelection();
            if (!selection.rangeCount || selection.isCollapsed) return;
            const range = selection.getRangeAt(0);
            const span = document.createElement('span');
            span.style.fontSize = factor + 'em';
            try { range.surroundContents(span); }
            catch (e) { span.appendChild(range.extractContents()); range.insertNode(span); }
            selection.removeAllRanges();
            const newRange = document.createRange();
            newRange.selectNodeContents(span);
            selection.addRange(newRange);
        }

        document.addEventListener('selectionchange', () => setTimeout(place, 0));
        window.addEventListener('scroll', () => { if (bar.style.display !== 'none') place(); }, true);
        bar.addEventListener('mousedown', (event) => event.preventDefault()); // selectie behouden

        bar.querySelectorAll('button').forEach((button) => button.addEventListener('click', () => {
            if (!editor) return;
            editor.focus();
            const command = button.dataset.fmt;
            if (command === 'bold') document.execCommand('bold');
            else if (command === 'big') wrapFontSize(1.2);
            else if (command === 'small') wrapFontSize(0.83);
            editor.dispatchEvent(new Event('input', { bubbles: true }));
            setTimeout(place, 0);
        }));
    })();
</script>
