import type { Component } from 'vue'

// Listados públicos de entidades de ESTE juego (guía §9): patrón genérico
// "índice + detalle por slug". Cada sección declara su endpoint público, su
// segmento de URL por locale (debe casar con el sitemap del backend), la
// clave i18n del título y los componentes de tarjeta y detalle (reciben
// { item, locale }). Las vistas EntityIndexView/EntityDetailView hacen el
// resto: fetch, canónica por locale (DC-12) y SEO (useHead).
export interface EntitySection {
  key: string
  endpoint: string
  paths: Record<string, string>
  titleKey: string
  item: Component
  detail: Component
  /** Clave del PreviewRegistry si la entidad puede añadirse a la colección
   *  "para imprimir" (botón ＋ en el índice y el detalle). */
  collectible?: string
}

// Añade aquí las secciones de tu juego, p. ej.:
// {
//   key: 'cartas',
//   endpoint: '/cartas',
//   paths: { es: 'cartas', eu: 'kartak', en: 'cards' },
//   titleKey: 'entities.cartas',
//   item: CartaItem,
//   detail: CartaDetail,
//   collectible: 'carta',
// }
export const entitySections: EntitySection[] = []

/** Todos los segmentos de URL (para el patrón de la ruta). */
export function sectionPattern(): string {
  return [...new Set(entitySections.flatMap((s) => Object.values(s.paths)))].join('|')
}

/** La sección a la que pertenece un segmento de URL (en cualquier locale). */
export function sectionFor(segment: string): EntitySection | null {
  return entitySections.find((s) => Object.values(s.paths).includes(segment)) ?? null
}
