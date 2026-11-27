/* Renovion Groep: huisstijl voor documenten richting de klant (het briefpapier).
   Eén opmaak voor alles wat de deur uit gaat: facturen en de documenten uit de
   documentgenerator. Dit bestand kent de staat niet; app.js geeft de gegevens mee.
   De bedrijfsgegevens, het logo en de kleuren staan in S.prefs.huis (Documenten > Huisstijl). */
import '../../css/huisstijl.css';

export const STD = {
  naam: 'Renovion Groep BV', slogan: 'Uw bouwproject, onze zorg.',
  adres: 'Mercatorweg 28', pc: '6827 DC Arnhem', tel: '+31 6 395 353 00', mail: 'info@renovion.nl', web: '',
  kvk: '95384782', btw: '', iban: '', accent: '#F26A1B', donker: '#0D1A2E', logo: '',
};
/* velden van het formulier Huisstijl: sleutel, label, voorbeeld */
export const VELDEN = [
  ['naam', 'Bedrijfsnaam', 'Renovion Groep BV'], ['slogan', 'Slogan onder het logo', 'Uw bouwproject, onze zorg.'],
  ['adres', 'Adres', 'Mercatorweg 28'], ['pc', 'Postcode en plaats', '6827 DC Arnhem'],
  ['tel', 'Telefoon', '+31 6 395 353 00'], ['mail', 'E-mail', 'info@renovion.nl'], ['web', 'Website', 'www.renovion.nl'],
  ['kvk', 'KvK-nummer', '95384782'], ['btw', 'Btw-nummer', 'NL000000000B01'], ['iban', 'IBAN', 'NL00 BANK 0000 0000 00'],
];
const KLEUR = /^#[0-9a-f]{6}$/i;
export const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/* de huisstijl zoals hij geldt: de standaard, met wat er in de instellingen is ingevuld */
export function org(prefs, concept) {
  const h = Object.assign({}, (prefs && prefs.huis) || {}, concept || {});
  const o = { ...STD };
  for (const k of Object.keys(STD)) if (typeof h[k] === 'string') o[k] = h[k].trim();
  if (!o.naam) o.naam = STD.naam;
  if (!KLEUR.test(o.accent)) o.accent = STD.accent;
  if (!KLEUR.test(o.donker)) o.donker = STD.donker;
  return o;
}

export function logo(o) {
  if (o.logo) return `<img class="hs-logo" src="${esc(o.logo)}" alt="${esc(o.naam)}">`;
  const s = (o.slogan || '').toUpperCase();
  return `<svg class="hs-logo" viewBox="70 88 578 ${s ? 160 : 126}" role="img" aria-label="${esc(o.naam)}"><path d="M117 205H80V136L140 99L200 136V152" fill="none" stroke="${o.accent}" stroke-width="7"/><text x="124" y="205" textLength="288" lengthAdjust="spacingAndGlyphs" font-family="Outfit, 'Plus Jakarta Sans', sans-serif" font-size="70" font-weight="500" fill="${o.donker}">Renovion</text><text x="428" y="205" textLength="204" lengthAdjust="spacingAndGlyphs" font-family="Outfit, 'Plus Jakarta Sans', sans-serif" font-size="70" font-weight="700" fill="${o.accent}">Groep</text>${s ? `<text x="128" y="241" font-family="'Plus Jakarta Sans', sans-serif" font-size="19" font-weight="600" ${s.length > 16 && s.length < 44 ? 'textLength="504" lengthAdjust="spacing"' : 'letter-spacing="3"'} fill="${o.donker}">${esc(s)}</text>` : ''}</svg>`;
}

/* de hoek linksboven: donker vlak, schuine band in de accentkleur en een veld met punten */
function hoek(o) {
  let dots = '';
  for (let r = 0; r < 10; r++) for (let c = 0; c < 5; c++) dots += `<circle cx="${178 + c * 11}" cy="${52 + r * 13}" r="1.15"/>`;
  return `<svg class="hs-hoek" viewBox="0 0 245 245" aria-hidden="true"><polygon points="0,0 186,0 0,212" fill="${o.donker}"/><polygon points="186,0 214,0 0,244 0,212" fill="${o.accent}"/><g fill="#8E97A6">${dots}</g></svg>`;
}

const rij = (l, v) => v == null || v === '' ? '' : `<div><dt>${esc(l)}</dt><dd>${v}</dd></div>`;

/* Het document. d = { aan:{naam,regels,mail}, titel, label, meta:[[label,html]], kv:[[label,html]], body, noot, voet:[regels rechts] }
   Waarden in meta en kv zijn html: de aanroeper zet er esc() omheen. */
export function doc(o, d) {
  d = d || {};
  const aan = d.aan || {};
  const adres = [o.adres, o.pc].filter(Boolean);
  const v1 = [[o.adres, o.pc].filter(Boolean).join(', '), o.kvk && 'KvK ' + o.kvk, o.btw && 'Btw ' + o.btw].filter(Boolean);
  const v2 = [o.mail, o.web, o.tel].filter(Boolean);
  const vr = [o.iban && 'IBAN ' + o.iban, ...(d.voet || [])].filter(Boolean);
  const sp = '<tr><td><div class="hs-sp"></div></td></tr>';
  return `<article class="hs" style="--hs-a:${o.accent};--hs-d:${o.donker}">${hoek(o)}<table class="hs-pg" role="presentation"><thead>${sp}</thead><tfoot>${sp}</tfoot><tbody><tr><td><div class="hs-vel">`
    + `<header class="hs-kop">${logo(o)}</header>`
    + `<address class="hs-aan"><b>${esc(aan.naam || '')}</b>${(aan.regels || []).filter(Boolean).map(r => `<span>${esc(r)}</span>`).join('')}${aan.mail ? `<small>${esc(aan.mail)}</small>` : ''}</address>`
    + `<div class="hs-titel"><h1>${esc(d.titel || 'Document')}${d.label ? `<em>${esc(d.label)}</em>` : ''}</h1><b>${esc(o.naam)}</b></div>`
    + `<div class="hs-meta"><dl>${(d.meta || []).map(([l, v]) => rij(l + ':', v)).join('')}</dl><div class="hs-van">${adres.map(r => `<span>${esc(r)}</span>`).join('')}</div></div>`
    + ((d.kv || []).some(([, v]) => v) ? `<dl class="hs-kv hs-kv-top">${d.kv.map(([l, v]) => rij(l, v)).join('')}</dl>` : '<hr class="hs-hr">')
    + `<div class="hs-body">${d.body || ''}</div>`
    + (d.noot ? `<p class="hs-noot">${d.noot}</p>` : '')
    + `</div></td></tr></tbody></table>`
    + `<footer class="hs-voet"><div class="hs-voet-in"><div><b>${esc(o.naam)}</b><span>${v1.map(esc).join('<i>|</i>')}</span><span>${v2.map(esc).join('<i>|</i>')}</span></div><div class="r">${vr.map(r => `<span>${esc(r)}</span>`).join('')}</div></div></footer></article>`;
}

/* tabel met regels en de optelling eronder; bedragen zijn al opgemaakt.
   t = { regels:[{t,sub,bedrag}], totalen:[[label,bedrag]], totaal:[label,bedrag] } */
export function tabel(t) {
  return `<table class="hs-t"><thead><tr><th>Omschrijving</th><th class="r">Bedrag</th></tr></thead><tbody>${(t.regels || []).map(r => `<tr><td><b>${esc(r.t)}</b>${r.sub ? `<small>${esc(r.sub)}</small>` : ''}</td><td class="r"><b>${esc(r.bedrag)}</b></td></tr>`).join('')}</tbody></table>`
    + `<div class="hs-tot">${(t.totalen || []).map(([l, v]) => `<div><span>${esc(l)}</span><span>${esc(v)}</span></div>`).join('')}${t.totaal ? `<div class="tot"><span>${esc(t.totaal[0])}</span><span>${esc(t.totaal[1])}</span></div>` : ''}</div>`;
}

/* ondertekening: twee partijen naast elkaar; signed = {naam, d} als er digitaal is getekend */
export function sig(...partijen) {
  return `<div class="hs-sig">${partijen.filter(Boolean).map(x => `<div><span class="hs-sig-rol">${esc(x.rol)}</span><b>${esc(x.naam || '')}</b>${x.signed
    ? `<span class="hs-sig-hand">${esc(x.signed.naam)}</span><i></i><small>Digitaal ondertekend op ${esc(x.signed.d)}</small>`
    : `<span class="hs-sig-ruimte"></span><i></i><small>Naam, datum en handtekening</small>`}</div>`).join('')}</div>`;
}

/* Tekst van een sjabloon naar opgemaakte html.
   - de eerste regel is de titel van het document;
   - een korte regel boven een alinea (of een regel met # ervoor) wordt een kopje;
   - regels als "Project: naam" onder elkaar worden een lijst met labels;
   - genummerde regels of regels met een streepje worden een lijst;
   - **tekst** wordt vet, {{variabele}} wordt ingevuld uit het dossier.
   mark = ingevulde variabelen laten oplichten (voorbeeld in de generator). */
const A = '\u0001', B = '\u0002', C = '\u0003', D = '\u0004', MERK = /[\u0001-\u0004]/g, HEEFT = /[\u0001-\u0004]/;
export function tekst(txt, v, mark) {
  const miss = [];
  const t = String(txt || '').replace(/\r/g, '').replace(/\{\{(\w+)\}\}/g, (m, k) => {
    const x = v && v[k];
    if (x == null || x === '') { if (!miss.includes(k)) miss.push(k); return C + m + D; }
    return String(x).split('\n').filter(l => l.trim()).map(l => A + l.trim() + B).join('\n');
  });
  const f = s => esc(s).replace(/\*\*(.+?)\*\*/g, '<b>$1</b>')
    .replace(/\u0001/g, mark ? '<span class="hs-var">' : '').replace(/\u0002/g, mark ? '</span>' : '')
    .replace(/\u0003/g, '<span class="hs-miss">').replace(/\u0004/g, '</span>');
  const kaal = s => s.replace(MERK, '');
  const isKop = l => !HEEFT.test(l) && l.length <= 48 && !/[.:;,!?]$/.test(l) && !/:\s/.test(l) && !/^(\d|[-•])/.test(l);
  const isKv = l => { const m = /^([^:\u0001-\u0004]{2,28}):\s+\S/.exec(l); return !!m && m[1].trim().split(/\s+/).length <= 3; };
  const isPunt = l => /^(\d+[.)]|[-•])\s+/.test(kaal(l));
  const blokken = t.split(/\n\s*\n/).map(b => b.split('\n').map(l => l.trim()).filter(Boolean)).filter(b => b.length);
  let titel = '';
  if (blokken.length && blokken[0].length === 1 && !HEEFT.test(blokken[0][0]) && blokken[0][0].length <= 70 && !/[.:,]$/.test(blokken[0][0])) titel = blokken.shift()[0].replace(/^#+\s*/, '');
  let html = '';
  for (const b of blokken) {
    let regels = b, kop = '';
    if (/^#+\s+/.test(regels[0])) { kop = regels[0].replace(/^#+\s+/, ''); regels = regels.slice(1); }
    else if (regels.length > 1 && isKop(regels[0])) { kop = regels[0]; regels = regels.slice(1); }
    html += `<section class="hs-blok">${kop ? `<h3>${f(kop)}</h3>` : ''}`;
    if (regels.length >= 2 && regels.every(isKv)) html += `<dl class="hs-kv">${regels.map(l => { const i = l.indexOf(':'); return `<div><dt>${f(l.slice(0, i))}</dt><dd>${f(l.slice(i + 1).trim())}</dd></div>`; }).join('')}</dl>`;
    else if (regels.length && regels.every(isPunt)) html += `<ul class="hs-lijst">${regels.map(l => `<li>${f(l)}</li>`).join('')}</ul>`;
    else if (regels.length) html += `<p>${regels.map(f).join('<br>')}</p>`;
    html += '</section>';
  }
  return { titel, html: `<div class="hs-tx">${html}</div>`, miss };
}

/* afdrukken of opslaan als pdf: alleen het document, zonder marges van de browser (de hoek loopt tot de rand) */
export function print() {
  const st = document.createElement('style'); st.textContent = '@page{size:A4;margin:0}'; document.head.appendChild(st);
  document.documentElement.classList.add('printing-hs');
  const klaar = () => { document.documentElement.classList.remove('printing-hs'); st.remove(); removeEventListener('afterprint', klaar); };
  addEventListener('afterprint', klaar);
  try { window.print(); } catch (_) { klaar(); return false; }
  setTimeout(klaar, 60000);
  return true;
}
