<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import type { RouteLocationRaw } from 'vue-router'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ChevronDown, FileDown, LayoutDashboard, LogIn, LogOut, Menu, X } from '@lucide/vue'
import { LocaleSelector, MotorBadge, ThemeSelector } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { entitySections } from '@/entities/registry'
import { DOWNLOAD_PATHS } from '@/router/downloads'
import { DICE_ROLLER_PATHS, LIFE_COUNTER_PATHS, TOOLS_PATHS } from '@/router/tools'
import { useAuthStore } from '@/stores/auth'
import { useCollectionStore } from '@/stores/collection'
import { useLocalesStore } from '@/stores/locales'
import { useSiteStore } from '@/stores/site'

// Cabecera pública estilo kontuan en dos líneas: arriba la marca y las
// acciones (admin si procede, descargas, entrar/usuario, idioma y tema);
// debajo, la barra de navegación CENTRADA, montada sobre el menú configurable
// del admin (GET /api/menu, doc 10 ampliado, rediseño sin grupos): mezcla
// páginas del CRM y rutas propias del juego (entitySections + herramientas +
// descargas). La jerarquía es la de las páginas — "si quieres un grupo, haz
// una página": una página con hijas (visibles y publicadas) despliega
// submenú al hover (chevron) y ella misma sigue siendo un enlace normal; en
// móvil TODO lo del header (salvo el logo) pasa a la barra lateral
// off-canvas, con los hijos indentados. Fija arriba, siempre visible. El
// endpoint viejo /pages/nav sigue vivo en el motor (retrocompat); este
// cascarón ya no lo usa.
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const locales = useLocalesStore()
const site = useSiteStore()
const collection = useCollectionStore()

// La barra derecha contextual (filtros de la vista) no se abre desde aquí:
// AppRightSidebar (@edc-motor/ui) trae su propia asa anclada a la barra,
// fija desde el borde inferior de esta cabecera hasta abajo.

interface MenuNode {
  id: number
  type: 'page' | 'route'
  route_key: string | null
  page: { id: number; title: Record<string, string>; slugs: Record<string, string> } | null
  children: MenuNode[]
}

interface NavEntry {
  id: number
  routeKey: string | null
  label: string
  // Siempre un enlace (páginas y rutas) — sin grupos, nada del menú carece
  // de destino propio.
  to: RouteLocationRaw
  children: NavEntry[]
}

const menu = ref<MenuNode[]>([])
const navOpen = ref(false)

// El submenú vive del :hover: al hacer clic en una hija seguiría abierto
// (el ratón no se ha movido). Se SUPRIME al navegar y se libera cuando el
// ratón sale del ítem.
const suppressedDropdown = ref<number | null>(null)

function closeDropdown(entryId: number) {
  suppressedDropdown.value = entryId
}

function releaseDropdown(entryId: number) {
  if (suppressedDropdown.value === entryId) suppressedDropdown.value = null
}

/** Primer texto disponible en el locale activo, con fallback al primero que haya. */
function firstText(map: Record<string, string> | null | undefined): string {
  if (!map) return ''
  return map[locales.current] || Object.values(map).find(Boolean) || ''
}

const downloadsSegment = computed(() => DOWNLOAD_PATHS[locales.current] ?? DOWNLOAD_PATHS.es)
const toolsSegment = computed(() => TOOLS_PATHS[locales.current] ?? TOOLS_PATHS.es)
const lifeCounterSegment = computed(() => LIFE_COUNTER_PATHS[locales.current] ?? LIFE_COUNTER_PATHS.es)
const diceRollerSegment = computed(() => DICE_ROLLER_PATHS[locales.current] ?? DICE_ROLLER_PATHS.es)

// Rutas propias del juego que el menú puede ofrecer (motor.menu.routes, doc
// 10 ampliado): cada clave se mapea a su destino + etiqueta. Una clave que
// llegue del backend sin mapa aquí se OMITE sin romper el resto del menú.
const routeMap = computed<Record<string, { label: string; to: RouteLocationRaw }>>(() => {
  const map: Record<string, { label: string; to: RouteLocationRaw }> = {}
  for (const section of entitySections) {
    map[section.key] = {
      label: t(section.titleKey),
      to: {
        name: 'entity-index',
        params: {
          locale: locales.current,
          section: section.paths[locales.current] || section.paths.es,
        },
      },
    }
  }
  map['life-counter'] = {
    label: t('tools.lifeCounter.title'),
    to: {
      name: 'life-counter',
      params: {
        locale: locales.current,
        tools: toolsSegment.value,
        tool: lifeCounterSegment.value,
      },
    },
  }
  map['dice-roller'] = {
    label: t('tools.diceRoller.title'),
    to: {
      name: 'dice-roller',
      params: {
        locale: locales.current,
        tools: toolsSegment.value,
        tool: diceRollerSegment.value,
      },
    },
  }
  map.downloads = {
    label: t('nav.downloads'),
    to: { name: 'downloads', params: { locale: locales.current, dl: downloadsSegment.value } },
  }
  return map
})

/** Nodo del menú -> entrada de nav (o null si no debe pintarse). Sin
 *  grupos: una página con hijas (visibles y publicadas) hace de desplegable
 *  — sale como enlace normal, con las hijas anidadas debajo. */
function buildEntry(node: MenuNode): NavEntry | null {
  if (node.type === 'page') {
    if (!node.page) return null
    const children = node.children.map(buildEntry).filter((e): e is NavEntry => e !== null)

    return {
      id: node.id,
      routeKey: null,
      label: firstText(node.page.title),
      to: { name: 'page', params: { locale: locales.current, slug: firstText(node.page.slugs) } },
      children,
    }
  }
  // Ruta: sin hijos (una ruta nunca es madre); si su clave es desconocida
  // para este front (aún no mapeada en routeMap), se omite sin romper el
  // resto del menú.
  const target = node.route_key ? routeMap.value[node.route_key] : undefined
  if (!target) return null

  return { id: node.id, routeKey: node.route_key, label: target.label, to: target.to, children: [] }
}

const navItems = computed(() => menu.value.map(buildEntry).filter((e): e is NavEntry => e !== null))

/** La página madre se colorea también si la ruta activa es una de sus hijas. */
function isActive(entry: NavEntry): boolean {
  if (entry.children.length) return entry.children.some(isActive)
  if (typeof entry.to !== 'object' || !('name' in entry.to)) return false
  if (route.name !== entry.to.name) return false
  const params = (entry.to.params ?? {}) as Record<string, unknown>
  for (const key of ['slug', 'section', 'dl', 'tool']) {
    if (key in params) return String(route.params[key] ?? '') === String(params[key])
  }
  return true
}

const userInitial = computed(() => auth.user?.name?.charAt(0)?.toUpperCase() ?? '?')

// Enlace a la administración (solo admin/editor). URL por env (kontuan).
const adminUrl = (import.meta.env.VITE_ADMIN_URL as string | undefined) || 'http://localhost:5174'
const canAccessAdmin = computed(() => auth.user?.can_access_admin === true)

/**
 * Ir al admin MANTENIENDO la sesión: se pide un código de traspaso (un solo
 * uso, 60 s) y el admin lo canjea por su propio token al cargar. Si falla,
 * se navega igual (login normal).
 */
async function goToAdmin(event: MouseEvent) {
  event.preventDefault()
  let url = adminUrl
  try {
    const code = await auth.requestHandoff()
    url = `${adminUrl}/?handoff=${code}`
  } catch {
    // sin código: el admin pedirá login
  }
  window.location.href = url
}

/** Cambia el idioma NAVEGANDO: el prefijo de la URL manda (DC-12). */
function switchLocale(code: string) {
  if (code === locales.current) return
  router.push({
    name: (route.name as string) || 'home',
    params: { ...route.params, locale: code },
    query: route.query,
  })
}

async function logout() {
  await auth.logout()
  router.push({ name: 'home', params: { locale: locales.current } })
}

// El panel móvil se cierra al navegar.
watch(
  () => route.fullPath,
  () => {
    navOpen.value = false
  },
)

onMounted(async () => {
  collection.load()
  await locales.load()
  try {
    const { data } = await api.get('/menu')
    menu.value = data.data
  } catch {
    // sin menú: la cabecera sigue funcionando (marca, acciones, idioma…)
  }
})
</script>

<template>
  <header class="site-header">
    <!-- Línea 1: marca + acciones (solo escritorio; en móvil, burger + logo) -->
    <div class="site-header__bar">
      <div class="site-header__bar-inner">
        <button
          class="site-header__burger"
          type="button"
          :aria-expanded="navOpen"
          :title="t('nav.menu')"
          @click="navOpen = !navOpen"
        >
          <X v-if="navOpen" :size="22" />
          <Menu v-else :size="22" />
        </button>

        <RouterLink
          :to="{ name: 'home', params: { locale: locales.current } }"
          class="site-header__brand"
          :title="site.title || 'EdC'"
        >
          <!-- Logo SVG inlineado: currentColor hereda el acento -->
          <!-- eslint-disable vue/no-v-html -- SVG subido por el admin -->
          <span
            v-if="site.logoInline"
            class="site-header__logo site-header__logo--svg"
            v-html="site.logoInline"
          />
          <!-- eslint-enable vue/no-v-html -->
          <img
            v-else-if="site.logoUrl"
            class="site-header__logo"
            :src="site.logoUrl"
            :alt="site.title || 'logo'"
          />
          <MotorBadge v-else :label="site.title || 'EdC'" />
        </RouterLink>

        <!-- Elementos sueltos, mismo gap para todos (sin grupos) -->
        <div class="site-header__actions">
          <a
            v-if="canAccessAdmin"
            class="site-header__collection"
            :href="adminUrl"
            :title="t('nav.admin')"
            @click="goToAdmin"
          >
            <LayoutDashboard :size="20" />
          </a>
          <RouterLink
            class="site-header__collection"
            :to="{ name: 'downloads', params: { locale: locales.current, dl: downloadsSegment } }"
            :title="t('nav.downloads')"
          >
            <FileDown :size="20" />
            <span v-if="collection.count" class="site-header__collection-count">
              {{ collection.count }}
            </span>
          </RouterLink>

          <!-- Entrar / usuario: SIEMPRE en la cabecera (patrón kontuan) -->
          <template v-if="auth.isAuthenticated">
            <RouterLink
              class="site-header__user"
              :to="{ name: 'account', params: { locale: locales.current } }"
              :title="t('nav.account')"
            >
              <span class="site-header__user-name">{{ auth.user?.name }}</span>
            </RouterLink>
            <button
              class="site-header__logout"
              type="button"
              :title="t('nav.logout')"
              @click="logout"
            >
              <LogOut :size="18" />
            </button>
          </template>
          <RouterLink
            v-else
            class="site-header__login"
            :to="{ name: 'login', params: { locale: locales.current } }"
          >
            {{ t('nav.login') }}
          </RouterLink>

          <!-- Preferencias: idioma + tema -->
          <LocaleSelector
            :model-value="locales.current"
            :locales="locales.locales"
            @update:model-value="switchLocale"
          />
          <ThemeSelector />
        </div>
      </div>
    </div>

    <!-- Línea 2 (escritorio): la barra de navegación, centrada -->
    <nav class="site-header__nav">
      <ul class="site-header__list">
        <li
          v-for="entry in navItems"
          :key="entry.id"
          class="site-header__item"
          :class="{
            'has-children': entry.children.length,
            'is-suppressed': suppressedDropdown === entry.id,
          }"
          @mouseleave="releaseDropdown(entry.id)"
        >
          <RouterLink
            class="site-header__link"
            :class="{ 'is-active': isActive(entry) }"
            :to="entry.to"
          >
            {{ entry.label }}
            <ChevronDown v-if="entry.children.length" :size="14" class="site-header__chevron" />
          </RouterLink>
          <!-- Submenú (hover / focus-within, patrón CDL): se cierra al elegir.
               Sin grupos: son las hijas de esta página (pages.parent_id). -->
          <ul v-if="entry.children.length" class="site-header__dropdown">
            <li v-for="child in entry.children" :key="child.id">
              <RouterLink
                class="site-header__dropdown-link"
                :to="child.to"
                @click="closeDropdown(entry.id)"
                >{{ child.label }}</RouterLink
              >
            </li>
          </ul>
        </li>
      </ul>
    </nav>

    <!-- Barra lateral (móvil): TODO lo del header salvo el logo -->
    <aside class="site-sidebar" :class="{ 'is-open': navOpen }">
      <div class="site-sidebar__prefs">
        <LocaleSelector
          :model-value="locales.current"
          :locales="locales.locales"
          @update:model-value="switchLocale"
        />
        <ThemeSelector />
      </div>
      <hr class="site-sidebar__divider" />

      <ul class="site-sidebar__list">
        <li v-for="entry in navItems" :key="entry.id">
          <RouterLink class="site-header__link" :to="entry.to">
            {{ entry.label }}
            <span
              v-if="entry.routeKey === 'downloads' && collection.count"
              class="site-header__collection-count"
            >
              {{ collection.count }}
            </span>
          </RouterLink>
          <!-- Hijas (sin grupos): siempre desplegadas, con indentación (patrón CDL) -->
          <ul v-if="entry.children.length" class="site-sidebar__children">
            <li v-for="child in entry.children" :key="child.id">
              <RouterLink class="site-header__link" :to="child.to">
                {{ child.label }}
                <span
                  v-if="child.routeKey === 'downloads' && collection.count"
                  class="site-header__collection-count"
                >
                  {{ collection.count }}
                </span>
              </RouterLink>
            </li>
          </ul>
        </li>
        <li v-if="canAccessAdmin">
          <a class="site-header__link" :href="adminUrl" @click="goToAdmin">
            <LayoutDashboard :size="16" />
            {{ t('nav.admin') }}
          </a>
        </li>
      </ul>

      <hr class="site-sidebar__divider" />
      <div class="site-sidebar__user">
        <template v-if="auth.isAuthenticated">
          <RouterLink
            class="site-header__user"
            :to="{ name: 'account', params: { locale: locales.current } }"
          >
            <span class="site-header__avatar">{{ userInitial }}</span>
            <span class="site-header__user-name">{{ auth.user?.name }}</span>
          </RouterLink>
          <button
            class="site-header__logout"
            type="button"
            :title="t('nav.logout')"
            @click="logout"
          >
            <LogOut :size="18" />
          </button>
        </template>
        <RouterLink
          v-else
          class="site-header__login"
          :to="{ name: 'login', params: { locale: locales.current } }"
        >
          <LogIn :size="16" />
          {{ t('nav.login') }}
        </RouterLink>
      </div>
    </aside>
  </header>
</template>
