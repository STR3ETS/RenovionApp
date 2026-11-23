/* =========================================================
   Renovion OS: de brug tussen de app en de server.

   De app (app.js) werkt met één staat S, net als het prototype. Deze brug
   knipt S op in regels (layout.json), stuurt na elke handeling alleen wat
   veranderde naar de server, haalt elke paar seconden op wat collega's
   deden en zet dat in S zonder het scherm onder iemands handen te
   vervangen. Foto's en bijlagen gaan als bestand naar de server; in S
   staat daarna alleen de link.
   ========================================================= */
import LAYOUT from './layout.json';

const BOOT = window.OS_BOOT || {};
const ITEMS = LAYOUT.items;
const MAPS = new Set(LAYOUT.maps);
const PERUSER = new Set(LAYOUT.perUser);
const POLL_MS = 8000;

let SERVER = new Map(); // pad -> JSON zoals de server hem heeft (canoniek, door JSON.stringify)
let REV = 0;
let SKELETON = {};
let getState = () => ({});
let queue = []; // wijzigingen van collega's die wachten tot de gebruiker klaar is
let timer = null;
let inflight = null;
let again = false;
let failures = 0;
const fileCache = new Map();
const IGNORE = new Set(); // paden die de server niet aan deze gebruiker geeft: niet steeds opnieuw proberen

export const hooks = {
    /** Mag de app nu bijgewerkt worden zonder iemand te storen? */
    idle: () => true,
    /** Er kwamen wijzigingen binnen; de app tekent het scherm opnieuw. */
    remote: () => {},
    /** Nieuwe accounts: wachtwoord één keer tonen. */
    accounts: () => {},
    /** Sessie verlopen. */
    expired: () => location.reload(),
};

/* ---------- Opknippen en weer in elkaar zetten ---------- */
const canon = v => (v == null ? null : JSON.stringify(JSON.parse(v)));
const isObj = v => v && typeof v === 'object' && !Array.isArray(v);

const NESTED = {};
for (const name of [...Object.keys(ITEMS), ...PERUSER]) {
    const i = name.indexOf('.');
    if (i > 0) (NESTED[name.slice(0, i)] = NESTED[name.slice(0, i)] || []).push(name.slice(i + 1));
}

function newKey() {
    return 'k' + Math.random().toString(36).slice(2, 10);
}

function itemRows(coll, list, rows) {
    if (!Array.isArray(list)) {
        rows.set(coll + '/#order', '[]');
        return;
    }
    const field = ITEMS[coll];
    const order = [];
    const seen = new Set();
    for (const it of list) {
        if (!it || typeof it !== 'object') continue;
        let k = field !== '_k' && it[field] != null && it[field] !== '' ? String(it[field]) : it._k;
        if (k == null || seen.has(k)) {
            if (!it._k || seen.has(it._k)) it._k = newKey();
            k = it._k;
        }
        seen.add(k);
        order.push(k);
        rows.set(coll + '/' + k, JSON.stringify(it));
    }
    rows.set(coll + '/#order', JSON.stringify(order));
}

/** S -> Map(pad -> JSON). Dezelfde regels als App\Os\Layout::toRows. */
export function toRows(S) {
    const rows = new Map();
    for (const top of Object.keys(S)) {
        const val = S[top];
        if (val === undefined || typeof val === 'function') continue;
        if (ITEMS[top]) itemRows(top, val, rows);
        else if (MAPS.has(top)) {
            for (const k of Object.keys(val || {})) if (val[k] !== undefined) rows.set(top + '/' + k, JSON.stringify(val[k]));
        } else if (PERUSER.has(top)) rows.set('~me/' + top, JSON.stringify(val));
        else if (NESTED[top] && isObj(val)) {
            const rest = {};
            for (const sub of Object.keys(val)) {
                const name = top + '.' + sub;
                if (ITEMS[name]) itemRows(name, val[sub], rows);
                else if (PERUSER.has(name)) rows.set('~me/' + name, JSON.stringify(val[sub]));
                else rest[sub] = val[sub];
            }
            rows.set(top, JSON.stringify(rest));
        } else rows.set(top, JSON.stringify(val));
    }
    return rows;
}

function setDotted(S, dotted, value) {
    const parts = dotted.split('.');
    const last = parts.pop();
    let t = S;
    for (const p of parts) {
        if (!isObj(t[p])) t[p] = {};
        t = t[p];
    }
    t[last] = value;
}
function getDotted(S, dotted) {
    return dotted.split('.').reduce((t, p) => (t == null ? undefined : t[p]), S);
}

function splitPath(path) {
    if (path.startsWith('~me/')) return { kind: 'user', name: path.slice(4) };
    const i = path.indexOf('/');
    if (i < 0) return { kind: 'whole', name: path };
    const coll = path.slice(0, i);
    const key = path.slice(i + 1);
    return { kind: MAPS.has(coll) ? 'map' : 'item', name: coll, key };
}

/** Map(pad -> JSON) -> S, uitgaand van de lege vorm (skeleton). */
export function fromRows(rows, skeleton) {
    const S = JSON.parse(JSON.stringify(skeleton || {}));
    const items = {};
    const orders = {};
    const nested = [];
    for (const [path, json] of rows) {
        if (json == null) continue;
        const p = splitPath(path);
        const v = JSON.parse(json);
        if (p.kind === 'whole') {
            if (isObj(v) && isObj(S[p.name]) && NESTED[p.name]) Object.assign(S[p.name], v);
            else S[p.name] = v;
        } else if (p.kind === 'user') nested.push([p.name, v]);
        else if (p.kind === 'map') {
            if (!isObj(S[p.name])) S[p.name] = {};
            S[p.name][p.key] = v;
        } else if (p.key === '#order') orders[p.name] = v;
        else (items[p.name] = items[p.name] || new Map()).set(p.key, v);
    }
    for (const coll of Object.keys(ITEMS)) {
        const byKey = items[coll] || new Map();
        const list = [];
        for (const k of orders[coll] || []) if (byKey.has(k)) { list.push(byKey.get(k)); byKey.delete(k); }
        for (const v of byKey.values()) list.push(v);
        setDotted(S, coll, list);
    }
    for (const [name, v] of nested) setDotted(S, name, v);
    return S;
}

/* ---------- Bijwerken met wat collega's deden ---------- */
function keyOfItem(coll, it) {
    const field = ITEMS[coll];
    return field !== '_k' && it && it[field] != null && it[field] !== '' ? String(it[field]) : it && it._k;
}

function patch(S, paths) {
    const colls = new Set();
    for (const path of paths) {
        const p = splitPath(path);
        const json = SERVER.get(path);
        const v = json == null ? undefined : JSON.parse(json);
        if (p.kind === 'item') colls.add(p.name);
        else if (p.kind === 'map') {
            if (!isObj(S[p.name])) S[p.name] = {};
            if (v === undefined) delete S[p.name][p.key];
            else S[p.name][p.key] = v;
        } else if (p.kind === 'user') setDotted(S, p.name, v);
        else if (v !== undefined) {
            const cur = S[p.name];
            if (isObj(cur) && isObj(v)) {
                const keep = NESTED[p.name] || [];
                for (const k of Object.keys(cur)) if (!(k in v) && !keep.includes(k)) delete cur[k];
                Object.assign(cur, v);
            } else S[p.name] = v;
        }
    }
    for (const coll of colls) {
        const cur = getDotted(S, coll);
        const arr = Array.isArray(cur) ? cur : [];
        const existing = new Map(arr.map(it => [keyOfItem(coll, it), it]));
        const prefix = coll + '/';
        const order = JSON.parse(SERVER.get(prefix + '#order') || '[]');
        const keys = [...order];
        for (const path of SERVER.keys()) if (path.startsWith(prefix) && path !== prefix + '#order' && !order.includes(path.slice(prefix.length))) keys.push(path.slice(prefix.length));
        const next = [];
        for (const k of keys) {
            const path = prefix + k;
            if (!SERVER.has(path)) continue;
            next.push(paths.has(path) || !existing.has(k) ? JSON.parse(SERVER.get(path)) : existing.get(k));
        }
        arr.length = 0;
        arr.push(...next);
        if (!Array.isArray(cur)) setDotted(S, coll, arr);
    }
}

function absorb(rows, rev) {
    if (rev > REV) REV = rev;
    if (rows && rows.length) queue.push(...rows);
    drain();
}

/** Zet binnengekomen wijzigingen in S, maar alleen als de gebruiker niet midden in iets zit. */
export function drain(force = false) {
    if (!queue.length) return;
    if (!force && (inflight || !hooks.idle())) return;
    const S = getState();
    const local = toRows(S);
    const changed = new Set();
    for (const [path, json] of queue.splice(0)) {
        const value = json == null ? null : canon(json);
        const known = SERVER.has(path) ? SERVER.get(path) : null;
        if (known === value) continue;
        const pending = (local.has(path) ? local.get(path) : null) !== known;
        if (value == null) SERVER.delete(path);
        else SERVER.set(path, value);
        // Zelf intussen ook gewijzigd? Dan wint de eigen wijziging; die gaat zo naar de server.
        if (!pending) changed.add(path);
    }
    if (!changed.size) return;
    patch(S, changed);
    hooks.remote(changed);
}

/* ---------- Bestanden ---------- */
async function sha(text) {
    const buf = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(text));
    return [...new Uint8Array(buf)].map(b => b.toString(16).padStart(2, '0')).join('');
}

function bigDataUrl(v) {
    return typeof v === 'string' && v.length > 4000 && v.startsWith('data:') && /^data:[^,]{0,120};base64,/.test(v.slice(0, 140));
}

function walk(node, visit, seen = new Set()) {
    if (!node || typeof node !== 'object' || seen.has(node)) return;
    seen.add(node);
    for (const k of Object.keys(node)) {
        const v = node[k];
        if (typeof v === 'string') visit(node, k, v);
        else if (v && typeof v === 'object') walk(v, visit, seen);
    }
}

/** Upload een data-URL als bestand; dezelfde inhoud gaat maar één keer. */
export async function uploadDataUrl(dataUrl, name) {
    const hash = await sha(dataUrl);
    if (fileCache.has(hash)) return fileCache.get(hash);
    const blob = await (await fetch(dataUrl)).blob();
    const url = await uploadBlob(blob, name);
    fileCache.set(hash, url);
    return url;
}

export async function uploadBlob(blob, name) {
    const fd = new FormData();
    const ext = (blob.type.split('/')[1] || 'bin').replace('jpeg', 'jpg').replace(/\+.*$/, '');
    fd.append('file', blob, name || 'bestand.' + ext);
    if (name) fd.append('name', name);
    const res = await api(BOOT.urls.files, { method: 'POST', form: fd });
    return res.url;
}

async function externalize(S) {
    const found = [];
    walk(S, (holder, key, v) => { if (bigDataUrl(v)) found.push([holder, key, v]); });
    for (const [holder, key, v] of found) {
        try {
            const url = await uploadDataUrl(v);
            if (holder[key] === v) holder[key] = url;
        } catch (e) {
            // Lukt het uploaden niet, dan blijft het bestand voorlopig in de staat staan.
        }
    }
}

/* ---------- Server ---------- */
export async function api(url, { method = 'GET', body, form } = {}) {
    const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': BOOT.csrf, 'X-Requested-With': 'XMLHttpRequest' };
    if (body) headers['Content-Type'] = 'application/json';
    const res = await fetch(url, { method, headers, body: form || (body ? JSON.stringify(body) : undefined), credentials: 'same-origin', cache: 'no-store' });
    if (res.status === 401 || res.status === 419) {
        hooks.expired();
        throw new Error('sessie');
    }
    if (!res.ok) {
        let msg = 'De server gaf een fout (' + res.status + ').';
        try { const j = await res.json(); if (j && j.message) msg = j.message; } catch (e) {}
        const err = new Error(msg);
        err.status = res.status;
        throw err;
    }
    return res.json();
}

/* ---------- Eén scherm tegelijk draait de automations ---------- */
let LEADER = false;
async function lease() {
    if (!BOOT.urls.lease) return;
    try {
        const r = await api(BOOT.urls.lease, { method: 'POST', body: {} });
        LEADER = !!r.leader;
    } catch (e) {
        LEADER = false;
    }
}
export const leader = () => LEADER;

/** Haal de staat op bij het openen van de app. */
export async function boot() {
    const [r] = await Promise.all([api(BOOT.urls.state), lease()]);
    SKELETON = r.skeleton || {};
    REV = r.rev || 0;
    SERVER = new Map();
    for (const [path, json] of r.rows) if (json != null) SERVER.set(path, canon(json));
    const S = fromRows(SERVER, SKELETON);
    // Een klant of partner krijgt maar een deel; wat de server niet gaf, probeert de app ook niet op te slaan.
    if (['Klant', 'Onderaannemer'].includes((BOOT.user || {}).rol)) for (const path of toRows(S).keys()) if (!SERVER.has(path)) IGNORE.add(path);
    return S;
}

export function attach(stateGetter) {
    getState = stateGetter;
    setInterval(() => { if (document.visibilityState === 'visible') pull(); }, POLL_MS);
    setInterval(() => drain(), 1200);
    setInterval(() => { if (document.visibilityState === 'visible') lease(); }, 45000);
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') pull(); });
    window.addEventListener('focus', () => pull());
    window.addEventListener('beforeunload', e => {
        if (hasUnsaved()) { flushNow(); e.preventDefault(); e.returnValue = ''; }
    });
    window.addEventListener('pagehide', () => { if (hasUnsaved()) flushNow(); });
}

function changesNow() {
    const local = toRows(getState());
    const changes = [];
    for (const [path, json] of local) if (SERVER.get(path) !== json && !IGNORE.has(path)) changes.push([path, json]);
    for (const path of SERVER.keys()) if (!local.has(path) && !IGNORE.has(path)) changes.push([path, null]);
    return changes;
}

export function hasUnsaved() {
    return !!inflight || !!timer || changesNow().length > 0;
}

/** Na elke handeling: kort wachten zodat een reeks wijzigingen in één keer gaat. */
export function schedule() {
    clearTimeout(timer);
    timer = setTimeout(() => { timer = null; flush(); }, 300);
}

function flushNow() {
    clearTimeout(timer);
    timer = null;
    const changes = changesNow();
    if (!changes.length) return;
    try {
        fetch(BOOT.urls.sync, {
            method: 'POST', keepalive: true, credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': BOOT.csrf },
            body: JSON.stringify({ since: REV, changes }),
        });
    } catch (e) {}
}

export function flush() {
    if (inflight) { again = true; return inflight; }
    inflight = (async () => {
        const S = getState();
        await externalize(S);
        const changes = changesNow();
        if (!changes.length) return;
        const res = await api(BOOT.urls.sync, { method: 'POST', body: { since: REV, changes } });
        for (const [path, json] of changes) {
            if (json == null) SERVER.delete(path);
            else SERVER.set(path, json);
        }
        // Wat de server weigerde of bijsneed, zetten we terug zoals het op de server staat.
        if ((res.restore || []).length) {
            queue.push(...res.restore);
            for (const [path, json] of res.restore) if (json == null) IGNORE.add(path);
        }
        failures = 0;
        status('');
        if (res.accounts && res.accounts.length) hooks.accounts(res.accounts);
        inflight = null;
        absorb(res.rows, res.rev);
    })().catch(err => {
        if (err.message === 'sessie') return;
        failures++;
        status(err.status === 422 || err.status === 413 ? 'Niet opgeslagen: ' + err.message : 'Niet opgeslagen, de verbinding is weg. We proberen het opnieuw.', true);
        setTimeout(schedule, Math.min(30000, 1500 * 2 ** failures));
    }).finally(() => {
        inflight = null;
        if (again) { again = false; schedule(); }
    });
    return inflight;
}

export async function pull() {
    if (inflight || timer) return;
    try {
        const res = await api(BOOT.urls.pull + '?since=' + REV);
        absorb(res.rows, res.rev);
        if (failures) { failures = 0; status(''); }
    } catch (e) {
        if (e.message !== 'sessie') status('Geen verbinding met de server. Je wijzigingen worden bewaard zodra die terug is.', true);
    }
}

/* ---------- Melding als opslaan niet lukt ---------- */
let pill = null;
function status(text, bad) {
    if (!pill) {
        pill = document.createElement('div');
        pill.className = 'os-sync';
        pill.setAttribute('role', 'status');
        document.body.appendChild(pill);
    }
    if (!text) { pill.classList.remove('on'); return; }
    pill.className = 'os-sync on' + (bad ? ' bad' : '');
    pill.innerHTML = '<i></i><span></span><button type="button">Opnieuw</button>';
    pill.querySelector('span').textContent = text;
    pill.querySelector('button').onclick = () => flush();
}

export const me = BOOT.user || {};
export const today = BOOT.today;
export const urls = BOOT.urls || {};
export const debug = !!BOOT.debug;
/** Staat er een AI-sleutel op de server (Nova en de agents met Claude)? */
export const ai = !!BOOT.ai;
export const rev = () => REV;
/** Wijzigingen die een andere aanroep (Nova, goedkeuring) meestuurde: meteen verwerken. */
export function ingest(res) {
    if (!res) return;
    absorb(res.rows || [], res.rev || 0);
    drain(true);
}
