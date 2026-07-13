import vue from '@vitejs/plugin-vue';
import path from 'path';
import { defineConfig } from 'vitest/config';

// Standalone Vitest config: reuse the Vue SFC plugin and the "@" alias, but
// skip the Laravel/Tailwind plugins (they expect a running dev server and are
// irrelevant to unit tests).
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./resources/js/test-setup.ts'],
        include: ['resources/js/**/*.{test,spec}.ts'],
    },
});
