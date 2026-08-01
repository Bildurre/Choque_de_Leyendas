import type { RouteLocationRaw } from 'vue-router'
import { entitySections } from './registry'

// Rutas hacia índices y singles desde las fichas (cluster app-singles):
// resuelven por CLAVE de sección (cards/heroes/factions/decks). Devuelven
// null si la sección aún no está registrada o falta el slug — el llamante
// pinta texto plano, sin enlace.

/** Ruta al índice de una sección: su PÁGINA del CRM (el slug de la página
 *  índice coincide con el segmento de la sección en cada locale, p. ej.
 *  /es/mazos — ya no hay ruta dedicada `entity-index`). `query` (opcional)
 *  precarga filtros del catálogo: las MISMAS claves de query string que
 *  escribe useFiltersQuery (p. ej. { race: '3' } en héroes), así el enlace
 *  aterriza en el índice ya filtrado, con su chip. */
export function sectionIndexRoute(
  sectionKey: string,
  locale: string,
  query?: Record<string, string>,
): RouteLocationRaw | null {
  const section = entitySections.find((s) => s.key === sectionKey)
  if (!section) return null
  return {
    name: 'page',
    params: { locale, slug: section.paths[locale] ?? Object.values(section.paths)[0] },
    ...(query ? { query } : {}),
  }
}

/** Ruta al single de una entidad por su slug (ya localizado por la API). */
export function sectionDetailRoute(
  sectionKey: string,
  slug: string | null | undefined,
  locale: string,
): RouteLocationRaw | null {
  const section = entitySections.find((s) => s.key === sectionKey)
  if (!section || !slug) return null
  return {
    name: 'entity-detail',
    params: {
      locale,
      section: section.paths[locale] ?? Object.values(section.paths)[0],
      slug,
    },
  }
}
