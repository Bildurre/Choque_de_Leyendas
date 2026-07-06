import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './assets/scss/main.scss'
import App from './App.vue'
import router from './router'
import { i18n } from '@/i18n'
import { useSiteStore } from '@/stores/site'

const app = createApp(App).use(createPinia()).use(router).use(i18n)

// Modo "acento aleatorio" (configuración de la web): la SPA no recarga al
// navegar, así que el sorteo del color se repite en cada cambio de ruta.
router.afterEach(() => {
  useSiteStore().onNavigate()
})

app.mount('#app')
