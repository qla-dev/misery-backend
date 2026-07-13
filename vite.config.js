import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'node:path';

export default defineConfig({
    root: 'resources/landing',
    base: '/dist/',
    plugins: [react()],
    build: {
        outDir: '../../public/dist',
        emptyOutDir: true,
        rollupOptions: {
            input: {
                home: resolve(process.cwd(), 'resources/landing/index.html'),
                cookies: resolve(process.cwd(), 'resources/landing/cookies/index.html'),
                privacy: resolve(process.cwd(), 'resources/landing/privacy/index.html'),
                terms: resolve(process.cwd(), 'resources/landing/terms/index.html'),
            },
        },
    },
});
