import type { RouteRecordRaw } from 'vue-router'
import es from '@/i18n/locales/es.json'
import eu from '@/i18n/locales/eu.json'
import en from '@/i18n/locales/en.json'

// Rutas con segmentos de path traducidos (patrón kontuan): la ruta del locale
// actual es el `path`; las de los demás locales se añaden como `alias` para que
// una URL en cualquier idioma resuelva al mismo nombre de ruta.
//
// Para cada entidad de TU juego, añade su par lista + single con el patrón del
// playground del motor (routes.<entidad> en los JSON de i18n + dos entradas
// aquí, con meta.nav/permission/breadcrumbs).
type RoutePaths = typeof es.routes

const translations: Record<string, RoutePaths> = { es: es.routes, eu: eu.routes, en: en.routes }

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
