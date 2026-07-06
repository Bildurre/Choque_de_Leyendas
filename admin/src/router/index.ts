import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { createLocalizedRoutes } from './i18n-paths'
import i18n from '@/i18n'

function currentLocale(): string {
  return (i18n.global.locale as unknown as { value: string }).value || 'es'
}

const router = createRouter({
  history: createWebHistory(),
  routes: createLocalizedRoutes(currentLocale()),
})

// Mapa de slugs por locale de la entidad abierta en un single (DC-11): la
// vista lo registra al cargar y lo limpia al salir, y el cambio de idioma
// redirige también el :slug (no solo los segmentos de la ruta).
let activeSlugMap: Record<string, string> | null = null

export function setActiveSlugMap(map: Record<string, string> | null) {
  activeSlugMap = map
}

/**
 * Al cambiar de idioma: reconstruye las rutas con los segmentos traducidos y
 * redirige a la misma ruta (por nombre) para que la URL pase al nuevo idioma.
 */
export function onLocaleChange(newLocale: string) {
  const from = router.currentRoute.value

  for (const route of router.getRoutes()) {
    if (route.name && router.hasRoute(route.name)) router.removeRoute(route.name)
  }
  for (const route of createLocalizedRoutes(newLocale)) router.addRoute(route)

  if (from.name) {
    const params = { ...from.params }
    if (params.slug && activeSlugMap?.[newLocale]) params.slug = activeSlugMap[newLocale]
    router.replace({ name: from.name, params, query: from.query })
  }
}

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  // Traspaso de sesión desde la web pública (?handoff=…, un solo uso): se
  // canjea por un token propio ANTES del guard de auth y se limpia la URL.
  if (to.query.handoff) {
    try {
      await auth.consumeHandoff(String(to.query.handoff))
    } catch {
      // código caducado/usado: seguirá al login normal
    }
    const query = { ...to.query }
    delete query.handoff
    return { path: to.path, query, hash: to.hash, replace: true }
  }

  if (auth.token && !auth.user) {
    try {
      await auth.fetchMe()
    } catch {
      /* token inválido */
    }
  }
  if (to.meta.admin) {
    if (!auth.isAuthenticated) return { name: 'login' }
    if (!auth.canAccessAdmin) {
      await auth.logout()
      return { name: 'login', query: { denied: '1' } }
    }
    // Permisos del motor por sección (doc 05): p. ej. un editor no entra en
    // páginas, configuración ni usuarios.
    if (to.meta.permission && !auth.can(to.meta.permission as string)) {
      return { name: 'dashboard' }
    }
  }
  if (to.meta.guest && auth.isAuthenticated && auth.canAccessAdmin) return { name: 'dashboard' }
})

export default router
