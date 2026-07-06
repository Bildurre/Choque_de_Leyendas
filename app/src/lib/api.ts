import { createApi } from '@edc-motor/ui'

export const TOKEN_KEY = 'edc_app_token'

export const api = createApi({
  baseURL: import.meta.env.VITE_API_URL,
  tokenKey: TOKEN_KEY,
  onUnauthorized: async () => {
    // Imports dinámicos para no crear el ciclo api ↔ store/router. Limpia el
    // estado en memoria (no solo localStorage) para que isAuthenticated caiga.
    const { useAuthStore } = await import('@/stores/auth')
    useAuthStore().clearSession()
    const { default: router } = await import('@/router')
    if (router.currentRoute.value.meta.auth) {
      const locale = String(router.currentRoute.value.params.locale ?? 'es')
      router.push({ name: 'login', params: { locale } })
    }
  },
})
