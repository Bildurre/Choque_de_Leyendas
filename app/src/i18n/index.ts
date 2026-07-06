import { createI18n } from 'vue-i18n'
import es from './locales/es.json'
import eu from './locales/eu.json'
import en from './locales/en.json'

// Textos de la PROPIA interfaz de la web pública (nav, listados…). El
// contenido (páginas, entidades) llega ya localizado de la API; el locale
// activo lo gobierna el prefijo de la URL (router) vía stores/locales.
export const i18n = createI18n({
  legacy: false,
  locale: localStorage.getItem('edc_app_locale') || 'es',
  fallbackLocale: 'es',
  messages: { es, eu, en },
})
