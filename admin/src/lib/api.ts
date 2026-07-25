import { createApi } from '@edc-motor/ui'
import { LOCALE_KEY } from '@/i18n'

export const TOKEN_KEY = 'edc_admin_token'

export const api = createApi({
  baseURL: import.meta.env.VITE_API_URL,
  tokenKey: TOKEN_KEY,
  // El locale ACTIVO del admin viaja en cada petición: el servidor busca y
  // ordena las listas por el idioma que se está viendo (no el del navegador).
  locale: () => localStorage.getItem(LOCALE_KEY),
  onUnauthorized: async () => {
    // Imports dinámicos para no crear el ciclo api ↔ store/router.
    const { useAuthStore } = await import('@/stores/auth')
    useAuthStore().clearSession()
    const { default: router } = await import('@/router')
    if (router.currentRoute.value.meta.admin) {
      router.push({ name: 'login' })
    }
  },
})
