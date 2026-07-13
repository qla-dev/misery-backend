import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    root: 'resources/landing',
    base: '/dist/',
    plugins: [react()],
    build: {
        outDir: '../../public/dist',
        emptyOutDir: true,
    },
});
