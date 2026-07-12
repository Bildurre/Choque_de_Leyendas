import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useHead } from '@edc-motor/ui'
import { sectionFor } from '@/entities/registry'
import { useLocalesStore } from '@/stores/locales'
import { useSiteStore } from '@/stores/site'

// Boilerplate común de las vistas ÍNDICE del juego (cartas, héroes,
// facciones, mazos): resolver la sección por el segmento de la URL,
// redirigir a la canónica del locale activo (patrón slug-map, DC-12) y
// montar el head SEO (canónica + hreflang de los segmentos por locale).
export function useIndexPage() {
  const route = useRoute()
  const router = useRouter()
  const locales = useLocalesStore()
  const site = useSiteStore()

  const segment = computed(() => String(route.params.section ?? ''))
  const section = computed(() => sectionFor(segment.value))

  /** Redirige al segmento del locale activo; true si ha redirigido. */
  function canonicalize(): boolean {
    const canonical = section.value?.paths[locales.current] ?? segment.value
    if (canonical !== segment.value) {
      router.replace({ params: { ...route.params, section: canonical } })
      return true
    }
    return false
  }

  /** SEO del índice; llamar tras cargar datos (y tras site.load()). */
  function applyHead(title: string) {
    const origin = window.location.origin
    useHead({
      title: site.documentTitle(title),
      description: site.description || undefined,
      canonical: `${origin}/${locales.current}/${segment.value}`,
      alternates: Object.fromEntries(
        Object.entries(section.value?.paths ?? {}).map(([code, path]) => [
          code,
          `${origin}/${code}/${path}`,
        ]),
      ),
    })
  }

  return { route, router, locales, site, segment, section, canonicalize, applyHead }
}
