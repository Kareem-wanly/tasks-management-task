import { fileURLToPath, URL } from 'node:url';
import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  // Where Laravel runs locally; the dev server forwards the backend's paths to it.
  const backend = loadEnv(mode, process.cwd(), '').BACKEND_URL || 'http://127.0.0.1:8000';
  // Keep the public host (ngrok): Laravel builds its Bitrix24 redirects from it.
  const toBackend = { target: backend, changeOrigin: false, xfwd: true };

  return {
    plugins: [react(), tailwindcss()],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },
    server: {
      // The SPA and the API on one public address (ngrok → this server), so the app also works inside
      // Bitrix24: Bitrix only talks to a frame on the app's registered address.
      allowedHosts: ['.ngrok-free.dev', '.ngrok-free.app', '.ngrok.app'],
      proxy: {
        '/api': toBackend,
        '/auth/bitrix/redirect': toBackend,
        '/auth/bitrix/callback': toBackend,
      },
    },
  };
});
