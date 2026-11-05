// App-iconen en splash voor iOS en Android (Renovion OS: open gevel + witte R) en de website-favicons
// (het huismerk op navy). Na een logowijziging opnieuw draaien: npm run mobile:assets
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const NAVY = '#070724';
const path = (relative) => fileURLToPath(new URL(relative, import.meta.url));
const logo = readFileSync(path('../public/images/renovion-logo.svg'), 'utf8');

const polygons = [...logo.matchAll(/<polygon class="cls-1" points="([^"]+)"/g)].map((match) => match[1]);
const letterR = logo.match(/<path class="cls-2" d="(M74,144\.1[^"]+)"/)[1];

// Bounding box van het huismerk in logo-coördinaten.
const MARK = { x: 28.5, y: 17.5, width: 128.6, height: 127.1 };

// scale = grootte van het merk t.o.v. de kortste zijde.
function render(width, height, scale, { background = true, round = false } = {}) {
    const s = (Math.min(width, height) * scale) / Math.max(MARK.width, MARK.height);
    const tx = (width - MARK.width * s) / 2 - MARK.x * s;
    const ty = (height - MARK.height * s) / 2 - MARK.y * s;
    const fill = round
        ? `<circle cx="${width / 2}" cy="${height / 2}" r="${width / 2}" fill="${NAVY}"/>`
        : `<rect width="${width}" height="${height}" fill="${NAVY}"/>`;

    const image = sharp(Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}">
        ${background ? fill : ''}
        <g transform="translate(${tx} ${ty}) scale(${s})">
            ${polygons.map((points) => `<polygon fill="#F85B0B" points="${points}"/>`).join('')}
            <path fill="#fff" d="${letterR}"/>
        </g>
    </svg>`));

    return (background && !round ? image.flatten({ background: NAVY }) : image).png();
}

// Renovion OS: de iOS/Android-app, naar het app-icoon van het prototype. Getekend op een vlak van 512 eenheden;
// de R komt uit het Renovion-logo. De website blijft het huismerk hierboven gebruiken.
const OS_NAVY = '#0D1A2E';
const OS_MARK = { x: 89, y: 78, width: 335, height: 344 };

function renderOs(width, height, scale, { background = true, round = false, glow = false } = {}) {
    const s = (Math.min(width, height) * scale) / Math.max(OS_MARK.width, OS_MARK.height);
    const tx = (width - OS_MARK.width * s) / 2 - OS_MARK.x * s;
    const ty = (height - OS_MARK.height * s) / 2 - OS_MARK.y * s;
    const paint = glow ? 'url(#glow)' : OS_NAVY;
    const fill = round
        ? `<circle cx="${width / 2}" cy="${height / 2}" r="${width / 2}" fill="${paint}"/>`
        : `<rect width="${width}" height="${height}" fill="${paint}"/>`;

    const image = sharp(Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}">
        <defs><radialGradient id="glow" cx="100%" cy="0%" r="75%"><stop offset="0" stop-color="#3A2B2C"/><stop offset="1" stop-color="${OS_NAVY}"/></radialGradient></defs>
        ${background ? fill : ''}
        <g transform="translate(${tx} ${ty}) scale(${s})">
            <path d="M185 408H103V205L257 92L410 205" fill="none" stroke="#F26A1B" stroke-width="28" stroke-linejoin="round"/>
            <path fill="#fff" transform="translate(${232 - 74 * 3.16} ${243 - 97.75 * 3.16}) scale(3.16)" d="${letterR}"/>
        </g>
    </svg>`));

    return (background && !round ? image.flatten({ background: OS_NAVY }) : image).png();
}

// Het merk vult ruim de helft van het icoon (0.54): met lucht eromheen oogt het rustiger op het beginscherm.
// iOS: één 1024px-icoon zonder transparantie (eis App Store) en een 2732px-splash (aspect fill).
const ios = '../ios/App/App/Assets.xcassets';
if (existsSync(path(ios))) {
    await renderOs(1024, 1024, 0.54, { glow: true }).toFile(path(`${ios}/AppIcon.appiconset/AppIcon-512@2x.png`));
    for (const file of ['splash-2732x2732.png', 'splash-2732x2732-1.png', 'splash-2732x2732-2.png']) {
        await renderOs(2732, 2732, 0.14).toFile(path(`${ios}/Splash.imageset/${file}`));
    }
    console.log('iOS-icoon en splash bijgewerkt');
}

// Android: adaptive icon (voorgrond op navy achtergrondkleur), losse iconen voor oude toestellen,
// en de splash-afbeeldingen op hun bestaande formaten.
const res = '../android/app/src/main/res';
if (existsSync(path(res))) {
    const densities = { mdpi: 1, hdpi: 1.5, xhdpi: 2, xxhdpi: 3, xxxhdpi: 4 };
    for (const [density, factor] of Object.entries(densities)) {
        const dir = `${res}/mipmap-${density}`;
        await renderOs(108 * factor, 108 * factor, 0.37, { background: false }).toFile(path(`${dir}/ic_launcher_foreground.png`));
        await renderOs(48 * factor, 48 * factor, 0.54, { glow: true }).toFile(path(`${dir}/ic_launcher.png`));
        await renderOs(48 * factor, 48 * factor, 0.48, { round: true, glow: true }).toFile(path(`${dir}/ic_launcher_round.png`));

        for (const orientation of ['port', 'land']) {
            const file = path(`${res}/drawable-${orientation}-${density}/splash.png`);
            const { width, height } = await sharp(readFileSync(file)).metadata();
            await renderOs(width, height, 0.28).toFile(file);
        }
    }
    const { width, height } = await sharp(readFileSync(path(`${res}/drawable/splash.png`))).metadata();
    await renderOs(width, height, 0.28).toFile(path(`${res}/drawable/splash.png`));

    // Meldingsicoon (24dp): Android toont alleen de vorm, dus wit op transparant.
    for (const [density, factor] of Object.entries(densities)) {
        const size = 24 * factor;
        const s = (size * 0.9) / Math.max(MARK.width, MARK.height);
        const tx = (size - MARK.width * s) / 2 - MARK.x * s;
        const ty = (size - MARK.height * s) / 2 - MARK.y * s;
        mkdirSync(path(`${res}/drawable-${density}`), { recursive: true });
        await sharp(Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}">
            <g transform="translate(${tx} ${ty}) scale(${s})" fill="#fff">
                ${polygons.map((points) => `<polygon points="${points}"/>`).join('')}
                <path d="${letterR}"/>
            </g>
        </svg>`)).png().toFile(path(`${res}/drawable-${density}/ic_stat_renovion.png`));
    }
    console.log('Android-iconen en splash bijgewerkt');
}

// Website: favicon (tabblad, bladwijzers) en "zet op beginscherm"-iconen, gekoppeld via
// resources/views/partials/favicon.blade.php. Klein formaat: merk groter, anders onleesbaar.
// lineWidthPx: hoe dik de gevellijnen op het eindformaat worden (de logolijn zelf is te dun voor 16px).
const NAVY_ICON = { background: NAVY, house: '#F85B0B', letter: '#fff' };
// Favicon (tabblad): alleen het oranje huisje, zonder letter en zonder achtergrond (keuze Dylan).
const FAVICON = { background: null, house: '#F85B0B', letter: null };

function webIconSvg(size, scale, radius, lineWidthPx = 0, colors = NAVY_ICON) {
    const s = (size * scale) / Math.max(MARK.width, MARK.height);
    const tx = (size - MARK.width * s) / 2 - MARK.x * s;
    const ty = (size - MARK.height * s) / 2 - MARK.y * s;
    const extra = Math.max(0, lineWidthPx / s - 3);
    const houseStroke = extra ? ` stroke="${colors.house}" stroke-width="${extra.toFixed(1)}" stroke-linejoin="miter"` : '';
    const letterStroke = extra ? ` stroke="${colors.letter}" stroke-width="${(extra / 3).toFixed(1)}"` : '';

    return `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">`
        + (colors.background ? `<rect width="${size}" height="${size}" rx="${size * radius}" fill="${colors.background}"/>` : '')
        + `<g transform="translate(${tx.toFixed(2)} ${ty.toFixed(2)}) scale(${s.toFixed(4)})">`
        + polygons.map((points) => `<polygon fill="${colors.house}"${houseStroke} points="${points}"/>`).join('')
        + (colors.letter ? `<path fill="${colors.letter}"${letterStroke} d="${letterR}"/>` : '')
        + '</g></svg>';
}

// .ico met PNG-afbeeldingen erin (ondersteund door alle huidige browsers).
function ico(pngs) {
    const header = Buffer.alloc(6 + 16 * pngs.length);
    header.writeUInt16LE(0, 0);
    header.writeUInt16LE(1, 2);
    header.writeUInt16LE(pngs.length, 4);
    let offset = header.length;
    pngs.forEach(({ size, data }, i) => {
        const entry = 6 + 16 * i;
        header.writeUInt8(size >= 256 ? 0 : size, entry);
        header.writeUInt8(size >= 256 ? 0 : size, entry + 1);
        header.writeUInt16LE(1, entry + 4);
        header.writeUInt16LE(32, entry + 6);
        header.writeUInt32LE(data.length, entry + 8);
        header.writeUInt32LE(offset, entry + 12);
        offset += data.length;
    });

    return Buffer.concat([header, ...pngs.map(({ data }) => data)]);
}

const web = '../public';
// De SVG tekent de browser meestal op 16-32px: lijnen afgestemd op ~32px.
// Zonder achtergrond mag het huisje bijna het hele vlak vullen.
writeFileSync(path(`${web}/favicon.svg`), webIconSvg(64, 0.94, 0, 4.2, FAVICON));
const icoPngs = await Promise.all([[16, 1.7], [32, 2.4], [48, 3]].map(async ([size, line]) => ({
    size,
    data: await sharp(Buffer.from(webIconSvg(size, 0.94, 0, line, FAVICON))).png().toBuffer(),
})));
writeFileSync(path(`${web}/favicon.ico`), ico(icoPngs));
// iOS rondt zelf af en wil geen transparantie; Android gebruikt de 192px-versie.
await render(180, 180, 0.62).toFile(path(`${web}/apple-touch-icon.png`));
await sharp(Buffer.from(webIconSvg(192, 0.62, 0.22))).png().toFile(path(`${web}/icon-192.png`));
console.log('Website-favicons bijgewerkt');
