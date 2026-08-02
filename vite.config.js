import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/frontend/home.css',
                'resources/js/frontend/home.js',
                'resources/js/frontend/luong/luong.js',
                'resources/js/frontend/nghiphep/nghiphep.js',
                'resources/js/frontend/chamcong/chamcong.js'
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
