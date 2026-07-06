import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, TOKEN_KEY } from '@/lib/api'

export interface User {
  id: number
  name: string
  email: string
  roles: string[]
  permissions: string[]
  can_access_admin: boolean
  email_verified: boolean
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem(TOKEN_KEY))
  const isAuthenticated = computed(() => !!token.value)
  const canAccessAdmin = computed(() => !!user.value?.can_access_admin)

  /** Permiso del motor (manage-game / manage-web / manage-users). */
  function can(permission: string): boolean {
    return user.value?.permissions?.includes(permission) ?? false
  }

  function setToken(value: string | null) {
    token.value = value
    if (value) localStorage.setItem(TOKEN_KEY, value)
    else localStorage.removeItem(TOKEN_KEY)
  }

  async function login(email: string, password: string) {
    const { data } = await api.post('/auth/login', { email, password })
    setToken(data.token)
    user.value = data.user
  }

  async function fetchMe() {
    const { data } = await api.get('/auth/me')
    user.value = data.data
  }

  /**
   * Canjea un código de traspaso (?handoff=…, un solo uso) llegado desde la
   * web pública por un token propio: la sesión "viaja" de web a admin.
   */
  async function consumeHandoff(code: string) {
    const { data } = await api.post('/auth/handoff/consume', { code })
    setToken(data.token)
    user.value = data.user
  }

  /** Pide un código de traspaso hacia la web pública (60 s, un solo uso). */
  async function requestHandoff(): Promise<string> {
    const { data } = await api.post('/auth/handoff')
    return data.code
  }

  /** Limpieza local (sin llamar a la API): para 401 del interceptor. */
  function clearSession() {
    setToken(null)
    user.value = null
  }

  async function logout() {
    try {
      await api.post('/auth/logout')
    } catch {
      // noop
    }
    clearSession()
  }

  return {
    user,
    token,
    isAuthenticated,
    canAccessAdmin,
    can,
    login,
    fetchMe,
    logout,
    clearSession,
    consumeHandoff,
    requestHandoff,
  }
})
