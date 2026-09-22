import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

// Site App: offline-first PWA used by site teams on phones and tablets.
export default defineConfig({
    plugins: [
        react(),
        tailwindcss(),
        VitePWA({
            registerType: 'prompt',
            includeAssets: ['icon.svg'],
            manifest: {
                name: 'Thabekhulu Site',
                short_name: 'Site',
                description: 'Capture site diaries, attendance, deliveries and incidents — even without signal.',
                theme_color: '#0E6B63',
                background_color: '#F6F6F3',
                display: 'standalone',
                start_url: '/',
                icons: [{ src: 'icon.svg', sizes: 'any', type: 'image/svg+xml', purpose: 'any maskable' }],
            },
            workbox: {
                // The app shell is cached; API calls are never cached (data syncs through the outbox).
                navigateFallback: '/index.html',
                globPatterns: ['**/*.{js,css,html,svg,woff2}'],
            },
        }),
    ],
    build: {
        rolldownOptions: {
            output: {
                // Keep framework code in long-lived cached chunks, separate from app code.
                advancedChunks: {
                    groups: [
                        { name: 'react', test: /node_modules[\\/](react|react-dom|scheduler)[\\/]/ },
                        { name: 'tanstack', test: /node_modules[\\/]@tanstack[\\/]/ },
                        { name: 'data', test: /node_modules[\\/](dexie|dexie-react-hooks|zod)[\\/]/ },
                    ],
                },
            },
        },
    },
    server: {
        port: 5174,
        proxy: {
            '/api': 'http://localhost:8080',
            '/sanctum': 'http://localhost:8080',
        },
    },
});
