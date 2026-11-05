import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

// De schermen die in de iOS/Android-app zelf zitten (mobile/app), gebouwd naar mobile/www (webDir van
// Capacitor). Het adres van de server gaat mee in de build:
//   RENOVION_API_URL=http://10.0.2.2:8000 npm run app:build
export default defineConfig({
    root: 'mobile/app',
    base: './',
    // Offline-scherm en logo (mobile/app/public) gaan ongewijzigd mee naar mobile/www.
    publicDir: 'public',
    define: {
        'import.meta.env.VITE_RENOVION_API': JSON.stringify(process.env.RENOVION_API_URL ?? 'https://dash.renovion.nl'),
    },
    plugins: [tailwindcss()],
    resolve: {
        // native.js laadt Turbo alleen voor serverpagina's; in de app zelf komt het nooit, dus niet meebundelen.
        alias: { '@hotwired/turbo': new URL('./mobile/app/empty.js', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1') },
    },
    build: {
        outDir: '../www',
        // Elke build begint leeg, zodat oude bestanden niet in de app blijven zitten.
        emptyOutDir: true,
        assetsInlineLimit: 0,
    },
});
