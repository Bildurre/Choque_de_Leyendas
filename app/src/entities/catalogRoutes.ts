import type { CatalogItem, CatalogRoutes } from '@edc-motor/ui'
import { entitySections } from './registry'

// Mapa clave del PreviewRegistry => fábricas de ruta (CONVENTIONS2 §7.5):
// resuelve los enlaces del bloque `related` (y de cualquier montaje directo
// de BlockRelated en singles). Se provee en main.ts con
// `app.provide(catalogRoutesKey, catalogRoutes)` (lo integra el ensamblaje).
// `counter` no se mapea: no tiene slug ni single público (se pinta sin
// enlace). `faction` tampoco: ya no está en el PreviewRegistry (sin PNG).
function sectionRoutes(sectionKey: string): CatalogRoutes[string] {
  const section = entitySections.find((s) => s.key === sectionKey)
  if (!section) return {}
  return {
    // El índice es la PÁGINA del CRM cuyo slug coincide con el segmento de
    // la sección (ya no hay ruta dedicada `entity-index`).
    index: (locale: string) => ({
      name: 'page',
      params: { locale, slug: section.paths[locale] ?? Object.values(section.paths)[0] },
    }),
    single: (item: CatalogItem, locale: string) =>
      item.slug
        ? {
            name: 'entity-detail',
            params: {
              locale,
              section: section.paths[locale] ?? Object.values(section.paths)[0],
              slug: item.slug,
            },
          }
        : null,
  }
}

export const catalogRoutes: CatalogRoutes = {
  card: sectionRoutes('cards'),
  hero: sectionRoutes('heroes'),
  'faction-deck': sectionRoutes('decks'),
}
