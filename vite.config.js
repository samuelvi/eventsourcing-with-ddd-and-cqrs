import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import symfonyPlugin from 'vite-plugin-symfony';

export default defineConfig({
    plugins: [react(), symfonyPlugin()],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        origin: 'http://localhost:5173',
        hmr: {
            host: 'localhost',
            port: 5173,
            protocol: 'ws'
        }
    },
    build: {
        rollupOptions: {
            input: {
                app: './assets/app.tsx'
            }
        }
    }
});
