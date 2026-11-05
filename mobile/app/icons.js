// Dezelfde iconen als de server (<x-icon>): de paden komen rechtstreeks uit icon.blade.php, zodat er
// één lijst blijft.
import source from '../../resources/views/components/icon.blade.php?raw';

const paths = Object.fromEntries(
    [...source.matchAll(/^\s*'([a-z0-9-]+)' => \[(.*)\],\s*$/gm)].map(([, name, list]) => [name, [...list.matchAll(/'([^']+)'/g)].map((match) => match[1])]),
);

export function iconSvg(name) {
    return `<svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke="currentColor" aria-hidden="true">${(paths[name] ?? [])
        .map((d) => `<path stroke-linecap="round" stroke-linejoin="round" d="${d}"/>`)
        .join('')}</svg>`;
}
