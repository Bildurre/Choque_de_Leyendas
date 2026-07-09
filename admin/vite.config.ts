import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'

// Panel de administración del juego (puerto 5174). PWA instalable (DC-01).
// La URL de la API vive en .env (VITE_API_URL), patrón kontuan; sin proxy.
export default defineConfig({
  plugins: [
    vue(),
    VitePWA({
      registerType: 'autoUpdate',
      manifest: {
        name: 'choque-de-leyendas Admin',
        short_name: 'choque-de-leyendas Admin',
        description: 'Panel de administración del juego',
        theme_color: '#6c5ce7',
        background_color: '#0f1115',
        display: 'standalone',
        start_url: '/',
        icons: [
          { src: '/pwa-192.png', sizes: '192x192', type: 'image/png' },
          { src: '/pwa-512.png', sizes: '512x512', type: 'image/png' },
          { src: '/pwa-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
        ],
      },
    }),
  ],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  // Los paquetes del motor son "fuente" (.ts/.vue sin compilar): si Vite los
  // pre-empaqueta, los .vue quedan fuera del chunk y los singletons (toast,
  // confirm, panel derecho) se duplican y la UI deja de reaccionar. Se
  // excluyen para que se sirvan como fuente, igual que en el monorepo.
  optimizeDeps: {
    exclude: ['@edc-motor/admin-kit', '@edc-motor/ui'],
  },
  server: { port: 5174 },
  css: {
    preprocessorOptions: {
      scss: {
        additionalData: '@use "tokens" as *;\n',
        loadPaths: [
          fileURLToPath(new URL('../node_modules/@edc-motor/ui/scss', import.meta.url)),
          fileURLToPath(new URL('../node_modules/@edc-motor/admin-kit/scss', import.meta.url)),
          fileURLToPath(new URL('../packages/shared/scss', import.meta.url)),
          fileURLToPath(new URL('./src/assets/scss', import.meta.url)),
        ],
      },
    },
  },
})
