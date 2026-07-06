import { createI18n } from 'vue-i18n'
import es from './locales/es.json'
import eu from './locales/eu.json'
import en from './locales/en.json'

// Clave de localStorage compartida con el store de locales (idioma único:
// UI + rutas + contenido).
export const LOCALE_KEY = 'edc_admin_locale'

const i18n = createI18n({
  legacy: false,
  locale: localStorage.getItem(LOCALE_KEY) || 'es',
  fallbackLocale: 'es',
  messages: { es, eu, en },
})

export default i18n
