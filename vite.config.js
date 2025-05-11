import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/epub.css', 
                'resources/js/app.js',
                'resources/js/epub.js',
                'resources/js/reader/reader.js',
            ],
            refresh: true,
        }),
    ],
});
