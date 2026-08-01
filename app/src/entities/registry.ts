import { defineAsyncComponent, type Component } from 'vue'

// Vistas single propias (cluster app-singles): las monta EntityDetailView
// como `section.detail` (props { item, locale }).
const CardSingleView = defineAsyncComponent(() => import('@/views/singles/CardSingleView.vue'))
const HeroSingleView = defineAsyncComponent(() => import('@/views/singles/HeroSingleView.vue'))
const FactionSingleView = defineAsyncComponent(
  () => import('@/views/singles/FactionSingleView.vue'),
)
const FactionDeckSingleView = defineAsyncComponent(
  () => import('@/views/singles/FactionDeckSingleView.vue'),
)

// Entidades públicas de ESTE juego (guía §9). Cada sección declara su
// endpoint público, su segmento de URL por locale (debe casar con el sitemap
// del backend y con el SLUG de su página índice del CRM) y el componente de
// detalle (recibe { item, locale }); el detalle lo
// monta EntityDetailView (fetch, canónica por locale DC-12 y SEO). El ÍNDICE
// ya no es una vista con ruta propia: es una página del CRM con el bloque
// «Índice de entidad» (EntityIndexBlock monta el catálogo de la sección).
export interface EntitySection {
  key: string
  endpoint: string
  paths: Record<string, string>
  detail: Component
  /** Clave del PreviewRegistry si la entidad puede añadirse a la colección
   *  "para imprimir" (botón ＋ en el índice y el detalle). */
  collectible?: string
  /** Cabecera del single como BLOQUE header del CRM (solo el título, con el
   *  «volver» dentro) en vez del banner genérico, y el cuerpo a lo ancho
   *  para que la ficha apile bloques. Devuelve el tinte del fondo a partir
   *  del ítem cargado (p. ej. el color de la entidad; null = gris del
   *  bloque por defecto). Ya lo declaran las cuatro secciones. */
  blockHeader?: (item: Record<string, unknown>) => { tint?: string | null }
}

/** Tinte del header: el color de la facción anidada del ítem (héroe/carta). */
function factionTint(item: Record<string, unknown>): { tint: string | null } {
  const faction = item.faction as { color?: unknown } | null | undefined
  return { tint: typeof faction?.color === 'string' ? faction.color : null }
}

// Secciones públicas del juego. Los segmentos por locale casan con el
// sitemap del backend (AppServiceProvider::sitemapEntries); eu queda
// comentado hasta que se active el locale (kartak / heroiak / fakzioak /
// sortak — el router solo enruta es/en).
export const entitySections: EntitySection[] = [
  {
    key: 'cards',
    endpoint: '/cards', // single: GET /api/cards/{slug} (EntityDetailView)
    paths: { es: 'cartas', en: 'cards' }, // eu: 'kartak' al activar el locale
    detail: CardSingleView,
    collectible: 'card',
    // Header-bloque del CRM tintado con el color de la facción de la carta.
    blockHeader: factionTint,
  },
  {
    key: 'heroes',
    endpoint: '/heroes',
    paths: { es: 'heroes', en: 'heroes' }, // eu: 'heroiak'
    detail: HeroSingleView,
    collectible: 'hero',
    // Header-bloque del CRM tintado con el color de la facción del héroe.
    blockHeader: factionTint,
  },
  {
    key: 'factions',
    endpoint: '/factions',
    paths: { es: 'facciones', en: 'factions' }, // eu: 'fakzioak'
    detail: FactionSingleView,
    // Header-bloque del CRM tintado con el color de la propia facción.
    blockHeader: (item) => ({ tint: typeof item.color === 'string' ? item.color : null }),
  },
  {
    key: 'decks',
    endpoint: '/faction-decks',
    paths: { es: 'mazos', en: 'decks' }, // eu: 'sortak'
    detail: FactionDeckSingleView,
    // Header-bloque del CRM con el tinte GRIS por defecto: un mazo puede
    // mezclar varias facciones, así que no se fuerza el color de ninguna.
    blockHeader: () => ({ tint: null }),
  },
]

/** Todos los segmentos de URL (para el patrón de la ruta). */
export function sectionPattern(): string {
  return [...new Set(entitySections.flatMap((s) => Object.values(s.paths)))].join('|')
}

/** La sección a la que pertenece un segmento de URL (en cualquier locale). */
export function sectionFor(segment: string): EntitySection | null {
  return entitySections.find((s) => Object.values(s.paths).includes(segment)) ?? null
}
