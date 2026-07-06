import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/lib/api'

export interface Locale {
  code: string
  name: string
}

const LOCALE_KEY = 'edc_app_locale'

// Idioma de la web pública: se envía a la API (?locale) y decide qué slug y
// título se pintan. Persistido en localStorage.
export const useLocalesStore = defineStore('locales', () => {
  const locales = ref<Locale[]>([])
  const defaultLocale = ref('es')
  const current = ref(localStorage.getItem(LOCALE_KEY) || 'es')

  function applyToApi(code: string) {
    api.defaults.params = { ...(api.defaults.params || {}), locale: code }
  }
  applyToApi(current.value)

  let inflight: Promise<void> | null = null

  function load(): Promise<void> {
    if (locales.value.length) return Promise.resolve()
    inflight ??= api
      .get('/locales')
      .then(({ data }) => {
        locales.value = data.locales
        defaultLocale.value = data.default
      })
      .finally(() => {
        inflight = null
      })
    return inflight
  }

  function setCurrent(code: string) {
    current.value = code
    localStorage.setItem(LOCALE_KEY, code)
    applyToApi(code)
  }

  return { locales, defaultLocale, current, load, setCurrent }
})
