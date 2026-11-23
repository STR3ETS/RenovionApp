import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/os.css', 'resources/css/shell.css', 'resources/js/os/main.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        // De app wacht met top-level await op de staat van de server.
        target: 'es2022',
        chunkSizeWarningLimit: 2000,
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
