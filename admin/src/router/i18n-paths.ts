import type { RouteRecordRaw } from 'vue-router'
import es from '@/i18n/locales/es.json'
// import eu from '@/i18n/locales/eu.json'
import en from '@/i18n/locales/en.json'

// Rutas con segmentos de path traducidos (patrón kontuan): la ruta del locale
// actual es el `path`; las de los demás locales se añaden como `alias` para que
// una URL en cualquier idioma resuelva al mismo nombre de ruta.
//
// Para cada entidad de TU juego, añade su par lista + single con el patrón del
// playground del motor (routes.<entidad> en los JSON de i18n + dos entradas
// aquí, con meta.nav/permission/breadcrumbs).
type RoutePaths = typeof es.routes

// const translations: Record<string, RoutePaths> = { es: es.routes, eu: eu.routes, en: en.routes }
const translations: Record<string, RoutePaths> = { es: es.routes, en: en.routes }

export const supportedLocales = Object.keys(translations)

function paths(locale: string): RoutePaths {
  return translations[locale] ?? es.routes
}

function buildAliases(build: (p: RoutePaths) => string, currentLocale: string): string[] {
  return supportedLocales
    .filter((l) => l !== currentLocale)
    .map((l) => build(translations[l] ?? es.routes))
}

export function createLocalizedRoutes(locale: string): RouteRecordRaw[] {
  const p = paths(locale)

  return [
    {
      path: `/${p.login}`,
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { guest: true },
      alias: buildAliases((t) => `/${t.login}`, locale),
    },
    {
      path: '/',
      name: 'dashboard',
      component: () => import('@/views/DashboardView.vue'),
      meta: { admin: true, nav: 'dashboard', titleKey: 'dashboard.title' },
    },
    // ——— Entidades del juego (altas/ediciones en modal; el detalle es ruta) ———
    {
      path: `/${p.factions}`,
      name: 'factions',
      component: () => import('@/views/factions/FactionsListView.vue'),
      alias: buildAliases((t) => `/${t.factions}`, locale),
      meta: {
        admin: true,
        nav: 'factions',
        permission: 'manage-game',
        titleKey: 'factions.title',
        breadcrumbs: [{ key: 'factions' }],
      },
    },
    {
      path: `/${p.factions}/:slug`,
      name: 'faction-single',
      component: () => import('@/views/factions/FactionSingleView.vue'),
      alias: buildAliases((t) => `/${t.factions}/:slug`, locale),
      meta: {
        admin: true,
        nav: 'factions',
        permission: 'manage-game',
        titleKey: 'factions.title',
        breadcrumbs: [{ key: 'factions', to: 'factions' }],
      },
    },
    {
      path: `/${p.heroes}`,
      name: 'heroes',
      component: () => import('@/views/heroes/HeroesListView.vue'),
      alias: buildAliases((t) => `/${t.heroes}`, locale),
      meta: {
        admin: true,
        nav: 'heroes',
        permission: 'manage-game',
        titleKey: 'heroes.title',
        breadcrumbs: [{ key: 'heroes' }],
      },
    },
    {
      path: `/${p.heroes}/:slug`,
      name: 'hero-single',
      component: () => import('@/views/heroes/HeroSingleView.vue'),
      alias: buildAliases((t) => `/${t.heroes}/:slug`, locale),
      meta: {
        admin: true,
        nav: 'heroes',
        permission: 'manage-game',
        titleKey: 'heroes.title',
        breadcrumbs: [{ key: 'heroes', to: 'heroes' }],
      },
    },
    {
      path: `/${p.cards}`,
      name: 'cards',
      component: () => import('@/views/cards/CardsListView.vue'),
      alias: buildAliases((t) => `/${t.cards}`, locale),
      meta: {
        admin: true,
        nav: 'cards',
        permission: 'manage-game',
        titleKey: 'cards.title',
        breadcrumbs: [{ key: 'cards' }],
      },
    },
    {
      path: `/${p.cards}/:slug`,
      name: 'card-single',
      component: () => import('@/views/cards/CardSingleView.vue'),
      alias: buildAliases((t) => `/${t.cards}/:slug`, locale),
      meta: {
        admin: true,
        nav: 'cards',
        permission: 'manage-game',
        titleKey: 'cards.title',
        breadcrumbs: [{ key: 'cards', to: 'cards' }],
      },
    },
    {
      path: `/${p.factionDecks}`,
      name: 'faction-decks',
      component: () => import('@/views/faction-decks/FactionDecksListView.vue'),
      alias: buildAliases((t) => `/${t.factionDecks}`, locale),
      meta: {
        admin: true,
        nav: 'factionDecks',
        permission: 'manage-game',
        titleKey: 'factionDecks.title',
        breadcrumbs: [{ key: 'factionDecks' }],
      },
    },
    {
      path: `/${p.factionDecks}/:slug`,
      name: 'faction-deck-single',
      component: () => import('@/views/faction-decks/FactionDeckSingleView.vue'),
      alias: buildAliases((t) => `/${t.factionDecks}/:slug`, locale),
      meta: {
        admin: true,
        nav: 'factionDecks',
        permission: 'manage-game',
        titleKey: 'factionDecks.title',
        breadcrumbs: [{ key: 'factionDecks', to: 'faction-decks' }],
      },
    },
    {
      path: `/${p.counters}`,
      name: 'counters',
      component: () => import('@/views/counters/CountersListView.vue'),
      alias: buildAliases((t) => `/${t.counters}`, locale),
      meta: {
        admin: true,
        nav: 'counters',
        permission: 'manage-game',
        titleKey: 'counters.title',
        breadcrumbs: [{ key: 'counters' }],
      },
    },
    {
      path: `/${p.heroAbilities}`,
      name: 'hero-abilities',
      component: () => import('@/views/hero-abilities/HeroAbilitiesListView.vue'),
      alias: buildAliases((t) => `/${t.heroAbilities}`, locale),
      meta: {
        admin: true,
        nav: 'heroAbilities',
        permission: 'manage-game',
        titleKey: 'heroAbilities.title',
        breadcrumbs: [{ key: 'heroAbilities' }],
      },
    },
    // ——— Taxonomías (solo lista; se editan en modal) ———
    {
      path: `/${p.heroSuperclasses}`,
      name: 'hero-superclasses',
      component: () => import('@/views/hero-superclasses/HeroSuperclassesListView.vue'),
      alias: buildAliases((t) => `/${t.heroSuperclasses}`, locale),
      meta: {
        admin: true,
        nav: 'heroSuperclasses',
        permission: 'manage-game',
        titleKey: 'heroSuperclasses.title',
        breadcrumbs: [{ key: 'heroSuperclasses' }],
      },
    },
    {
      path: `/${p.heroClasses}`,
      name: 'hero-classes',
      component: () => import('@/views/hero-classes/HeroClassesListView.vue'),
      alias: buildAliases((t) => `/${t.heroClasses}`, locale),
      meta: {
        admin: true,
        nav: 'heroClasses',
        permission: 'manage-game',
        titleKey: 'heroClasses.title',
        breadcrumbs: [{ key: 'heroClasses' }],
      },
    },
    {
      path: `/${p.heroRaces}`,
      name: 'hero-races',
      component: () => import('@/views/hero-races/HeroRacesListView.vue'),
      alias: buildAliases((t) => `/${t.heroRaces}`, locale),
      meta: {
        admin: true,
        nav: 'heroRaces',
        permission: 'manage-game',
        titleKey: 'heroRaces.title',
        breadcrumbs: [{ key: 'heroRaces' }],
      },
    },
    {
      path: `/${p.attackRanges}`,
      name: 'attack-ranges',
      component: () => import('@/views/attack-ranges/AttackRangesListView.vue'),
      alias: buildAliases((t) => `/${t.attackRanges}`, locale),
      meta: {
        admin: true,
        nav: 'attackRanges',
        permission: 'manage-game',
        titleKey: 'attackRanges.title',
        breadcrumbs: [{ key: 'attackRanges' }],
      },
    },
    {
      path: `/${p.attackSubtypes}`,
      name: 'attack-subtypes',
      component: () => import('@/views/attack-subtypes/AttackSubtypesListView.vue'),
      alias: buildAliases((t) => `/${t.attackSubtypes}`, locale),
      meta: {
        admin: true,
        nav: 'attackSubtypes',
        permission: 'manage-game',
        titleKey: 'attackSubtypes.title',
        breadcrumbs: [{ key: 'attackSubtypes' }],
      },
    },
    {
      path: `/${p.cardTypes}`,
      name: 'card-types',
      component: () => import('@/views/card-types/CardTypesListView.vue'),
      alias: buildAliases((t) => `/${t.cardTypes}`, locale),
      meta: {
        admin: true,
        nav: 'cardTypes',
        permission: 'manage-game',
        titleKey: 'cardTypes.title',
        breadcrumbs: [{ key: 'cardTypes' }],
      },
    },
    {
      path: `/${p.cardSubtypes}`,
      name: 'card-subtypes',
      component: () => import('@/views/card-subtypes/CardSubtypesListView.vue'),
      alias: buildAliases((t) => `/${t.cardSubtypes}`, locale),
      meta: {
        admin: true,
        nav: 'cardSubtypes',
        permission: 'manage-game',
        titleKey: 'cardSubtypes.title',
        breadcrumbs: [{ key: 'cardSubtypes' }],
      },
    },
    {
      path: `/${p.equipmentTypes}`,
      name: 'equipment-types',
      component: () => import('@/views/equipment-types/EquipmentTypesListView.vue'),
      alias: buildAliases((t) => `/${t.equipmentTypes}`, locale),
      meta: {
        admin: true,
        nav: 'equipmentTypes',
        permission: 'manage-game',
        titleKey: 'equipmentTypes.title',
        breadcrumbs: [{ key: 'equipmentTypes' }],
      },
    },
    {
      path: `/${p.equipmentSubtypes}`,
      name: 'equipment-subtypes',
      component: () => import('@/views/equipment-subtypes/EquipmentSubtypesListView.vue'),
      alias: buildAliases((t) => `/${t.equipmentSubtypes}`, locale),
      meta: {
        admin: true,
        nav: 'equipmentSubtypes',
        permission: 'manage-game',
        titleKey: 'equipmentSubtypes.title',
        breadcrumbs: [{ key: 'equipmentSubtypes' }],
      },
    },
    {
      path: `/${p.gameModes}`,
      name: 'game-modes',
      component: () => import('@/views/game-modes/GameModesListView.vue'),
      alias: buildAliases((t) => `/${t.gameModes}`, locale),
      meta: {
        admin: true,
        nav: 'gameModes',
        permission: 'manage-game',
        titleKey: 'gameModes.title',
        breadcrumbs: [{ key: 'gameModes' }],
      },
    },
    // ——— Configuraciones del juego ———
    {
      path: `/${p.heroAttributesConfig}`,
      name: 'hero-attributes-configuration',
      component: () =>
        import('@/views/hero-attributes-configuration/HeroAttributesConfigurationView.vue'),
      alias: buildAliases((t) => `/${t.heroAttributesConfig}`, locale),
      meta: {
        admin: true,
        nav: 'heroAttributesConfig',
        permission: 'manage-game',
        titleKey: 'heroAttributesConfig.title',
        breadcrumbs: [{ key: 'heroAttributesConfig' }],
      },
    },
    {
      path: `/${p.deckAttributesConfigs}`,
      name: 'deck-attributes-configurations',
      component: () =>
        import('@/views/deck-attributes-configurations/DeckAttributesConfigurationsListView.vue'),
      alias: buildAliases((t) => `/${t.deckAttributesConfigs}`, locale),
      meta: {
        admin: true,
        nav: 'deckAttributesConfigs',
        permission: 'manage-game',
        titleKey: 'deckAttributesConfigs.title',
        breadcrumbs: [{ key: 'deckAttributesConfigs' }],
      },
    },
    {
      path: `/${p.icons}`,
      name: 'icons',
      component: () => import('@/views/icons/IconsListView.vue'),
      alias: buildAliases((t) => `/${t.icons}`, locale),
      meta: {
        admin: true,
        nav: 'icons',
        permission: 'manage-game',
        titleKey: 'icons.title',
        breadcrumbs: [{ key: 'icons' }],
      },
    },
    {
      path: `/${p.previews}`,
      name: 'previews',
      component: () => import('@/views/previews/PreviewsView.vue'),
      alias: buildAliases((t) => `/${t.previews}`, locale),
      meta: {
        admin: true,
        nav: 'previews',
        permission: 'manage-game',
        titleKey: 'previewsManager.title',
        breadcrumbs: [{ key: 'previews' }],
      },
    },
    {
      path: `/${p.pages}`,
      name: 'pages',
      component: () => import('@/views/pages/PagesListView.vue'),
      alias: buildAliases((t) => `/${t.pages}`, locale),
      meta: {
        admin: true,
        nav: 'pages',
        permission: 'manage-web',
        titleKey: 'pages.title',
        breadcrumbs: [{ key: 'pages' }],
      },
    },
    {
      path: `/${p.pages}/:id`,
      name: 'page',
      component: () => import('@/views/pages/PageSingleView.vue'),
      alias: buildAliases((t) => `/${t.pages}/:id`, locale),
      meta: {
        admin: true,
        nav: 'pages',
        permission: 'manage-web',
        titleKey: 'pages.title',
        breadcrumbs: [{ key: 'pages', to: 'pages' }],
      },
    },
    {
      path: `/${p.pdfs}`,
      name: 'pdfs',
      component: () => import('@/views/pdfs/PdfsView.vue'),
      alias: buildAliases((t) => `/${t.pdfs}`, locale),
      meta: {
        admin: true,
        nav: 'pdfs',
        permission: 'manage-game',
        titleKey: 'pdfs.viewTitle',
        breadcrumbs: [{ key: 'pdfs' }],
      },
    },
    {
      path: `/${p.settings}`,
      name: 'settings',
      component: () => import('@/views/settings/SettingsView.vue'),
      alias: buildAliases((t) => `/${t.settings}`, locale),
      meta: {
        admin: true,
        nav: 'settings',
        permission: 'manage-web',
        titleKey: 'settings.title',
        breadcrumbs: [{ key: 'settings' }],
      },
    },
    {
      path: `/${p.backups}`,
      name: 'backups',
      component: () => import('@/views/backups/BackupsView.vue'),
      alias: buildAliases((t) => `/${t.backups}`, locale),
      meta: {
        admin: true,
        nav: 'backups',
        permission: 'manage-web',
        titleKey: 'backups.title',
        breadcrumbs: [{ key: 'backups' }],
      },
    },
    {
      path: `/${p.users}`,
      name: 'users',
      component: () => import('@/views/users/UsersView.vue'),
      alias: buildAliases((t) => `/${t.users}`, locale),
      meta: {
        admin: true,
        nav: 'users',
        permission: 'manage-users',
        titleKey: 'users.title',
        breadcrumbs: [{ key: 'users' }],
      },
    },
    // URLs desconocidas: al dashboard (evita la página en blanco).
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      redirect: { name: 'dashboard' },
    },
  ]
}
