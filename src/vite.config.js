import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import path from 'path';
import { defineConfig } from 'vite';

export default defineConfig(({ mode }) => ({
    plugins: [
        laravel({
            input: ['resources/js/app.ts', 'resources/sass/app.scss'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
            patterns: ['routes/**/**/*.php', 'app/**/*.php', '.env'],
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
            '@components': path.resolve(__dirname, './resources/js/components'),
        },
    },
    build: {
        sourcemap: mode === 'development',
    },
    server: {
        port: Number(process.env.VITE_PORT) || 5173,
    },
}));
