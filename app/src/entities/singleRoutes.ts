import type { RouteLocationRaw } from 'vue-router'
import { entitySections } from './registry'

// Rutas hacia índices y singles desde las fichas (cluster app-singles):
// resuelven por CLAVE de sección (cards/heroes/factions/decks) sobre las
// rutas genéricas del router. Devuelven null si la sección aún no está
// registrada o falta el slug — el llamante pinta texto plano, sin enlace.

/** Ruta al índice de una sección (p. ej. el índice de facciones). */
export function sectionIndexRoute(sectionKey: string, locale: string): RouteLocationRaw | null {
  const section = entitySections.find((s) => s.key === sectionKey)
  if (!section) return null
  return {
    name: 'entity-index',
    params: { locale, section: section.paths[locale] ?? Object.values(section.paths)[0] },
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
