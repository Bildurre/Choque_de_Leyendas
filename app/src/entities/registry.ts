import { defineAsyncComponent, type Component } from 'vue'

// Vistas índice propias (cluster app-indices): lazy para no engordar el
// bundle inicial (el router ya carga EntityIndexView en diferido).
const CardsIndexView = defineAsyncComponent(() => import('@/views/indices/CardsIndexView.vue'))
const HeroesIndexView = defineAsyncComponent(() => import('@/views/indices/HeroesIndexView.vue'))
const FactionsIndexView = defineAsyncComponent(
  () => import('@/views/indices/FactionsIndexView.vue'),
)
const FactionDecksIndexView = defineAsyncComponent(
  () => import('@/views/indices/FactionDecksIndexView.vue'),
)

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
  /** Vista índice PROPIA (búsqueda/paginación/pestañas): EntityIndexView le
   *  cede el paso. Sin ella se usa el listado genérico (fetch de `endpoint`
   *  + tarjeta `item`). */
  index?: Component
  /** Tarjeta del listado genérico; innecesaria si la sección trae `index`. */
  item?: Component
  detail: Component
  /** Clave del PreviewRegistry si la entidad puede añadirse a la colección
   *  "para imprimir" (botón ＋ en el índice y el detalle). */
  collectible?: string
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
    titleKey: 'entities.cards',
    index: CardsIndexView, // índice: GET /api/catalog/card
    detail: CardSingleView,
    collectible: 'card',
  },
  {
    key: 'heroes',
    endpoint: '/heroes',
    paths: { es: 'heroes', en: 'heroes' }, // eu: 'heroiak'
    titleKey: 'entities.heroes',
    index: HeroesIndexView, // índice: GET /api/catalog/hero
    detail: HeroSingleView,
    collectible: 'hero',
  },
  {
    key: 'factions',
    endpoint: '/factions',
    paths: { es: 'facciones', en: 'factions' }, // eu: 'fakzioak'
    titleKey: 'entities.factions',
    index: FactionsIndexView, // índice: GET /api/factions
    detail: FactionSingleView,
  },
  {
    key: 'decks',
    endpoint: '/faction-decks',
    paths: { es: 'mazos', en: 'decks' }, // eu: 'sortak'
    titleKey: 'entities.decks',
    index: FactionDecksIndexView, // índice: GET /api/faction-decks
    detail: FactionDeckSingleView,
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
