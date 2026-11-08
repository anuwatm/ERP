import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.tsx',
            refresh: true,
        }),
        react(),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (
                        id.includes('node_modules/react/') ||
                        id.includes('node_modules/react-dom/') ||
                        id.includes('node_modules/scheduler/') ||
                        id.includes('node_modules/@inertiajs/')
                    ) {
                        return 'vendor-react';
                    }
                    if (id.includes('node_modules/@headlessui/')) {
                        return 'vendor-ui';
                    }
                },
            },
        },
    },
});
