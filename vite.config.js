import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [laravel({ input: ['resources/css/store.css', 'resources/js/store.js'], refresh: true }), tailwindcss()],
    build: { manifest: 'manifest.json' },
});
