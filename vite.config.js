import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/frontend/luong/luong.js',
                'resources/js/frontend/nghiphep/nghiphep.js',
                'resources/js/frontend/chamcong/chamcong.js',
                'resources/js/frontend/nhanvien/nhanvien.js',
                'resources/js/frontend/luong/luongCreateUpdate.js',
                'resources/js/frontend/luong/luongHeSo.js',
                'resources/js/frontend/luong/luongHeSoCreateUpdate.js',
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
