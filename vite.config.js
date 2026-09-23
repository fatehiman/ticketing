import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // Two themes: RTL (Persian) and LTR (English). The layout loads one of them.
            input: ['resources/css/theme-rtl.css', 'resources/css/theme-ltr.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
