import { createApi } from '@edc-motor/ui'

export const TOKEN_KEY = 'edc_admin_token'

export const api = createApi({
  baseURL: import.meta.env.VITE_API_URL,
  tokenKey: TOKEN_KEY,
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
