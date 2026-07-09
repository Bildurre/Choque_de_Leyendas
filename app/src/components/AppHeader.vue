<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ChevronDown, FileDown, LayoutDashboard, LogIn, LogOut, Menu, X } from '@lucide/vue'
import { LocaleSelector, MotorBadge, ThemeSelector } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { entitySections } from '@/entities/registry'
import { DOWNLOAD_PATHS } from '@/router/downloads'
import { useAuthStore } from '@/stores/auth'
import { useCollectionStore } from '@/stores/collection'
import { useLocalesStore } from '@/stores/locales'
import { useSiteStore } from '@/stores/site'

// Cabecera pública estilo kontuan en dos líneas: arriba la marca y las
// acciones (admin si procede, descargas, entrar/usuario, idioma y tema);
// debajo, la barra de navegación CENTRADA. Las páginas con hijas publicadas
// despliegan submenú al hover (chevron, patrón CDL); en móvil TODO lo del
// header (salvo el logo) pasa a la barra lateral off-canvas, con las hijas
// indentadas. Se oculta al bajar y asoma al subir.
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const locales = useLocalesStore()
const site = useSiteStore()
const collection = useCollectionStore()

interface NavPage {
  id: number
  title: Record<string, string>
  slugs: Record<string, string>
  is_home: boolean
  children?: Omit<NavPage, 'is_home' | 'children'>[]
}

const pages = ref<NavPage[]>([])
const navOpen = ref(false)
const hidden = ref(false)

// El submenú vive del :hover: al hacer clic en una hija seguiría abierto
// (el ratón no se ha movido). Se SUPRIME al navegar y se libera cuando el
// ratón sale del ítem.
const suppressedDropdown = ref<number | null>(null)

function closeDropdown(pageId: number) {
  suppressedDropdown.value = pageId
}

function releaseDropdown(pageId: number) {
  if (suppressedDropdown.value === pageId) suppressedDropdown.value = null
}

/** El padre se colorea también cuando la ruta activa es una de sus hijas. */
function isParentActive(page: { slug: string; children: { slug: string }[] }): boolean {
  if (route.name !== 'page') return false
  const slug = String(route.params.slug ?? '')
  return slug === page.slug || page.children.some((child) => child.slug === slug)
}

function navEntry(p: { id: number; title: Record<string, string>; slugs: Record<string, string> }) {
  return {
    id: p.id,
    label: p.title[locales.current] || Object.values(p.title)[0] || '',
    slug: p.slugs[locales.current] || Object.values(p.slugs)[0] || '',
  }
}

const navPages = computed(() =>
  pages.value
    .filter((p) => !p.is_home)
    .map((p) => ({
      ...navEntry(p),
      children: (p.children ?? []).map(navEntry),
    })),
)

const navSections = computed(() =>
  entitySections.map((s) => ({
    key: s.key,
    label: t(s.titleKey),
    section: s.paths[locales.current] || s.paths.es,
  })),
)

const downloadsSegment = computed(() => DOWNLOAD_PATHS[locales.current] ?? DOWNLOAD_PATHS.es)

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

// Ocultar al bajar, enseñar al subir.
let lastY = 0
function onScroll() {
  const y = window.scrollY
  hidden.value = y > 80 && y > lastY && !navOpen.value
  lastY = y
}

// El panel móvil se cierra al navegar.
watch(
  () => route.fullPath,
  () => {
    navOpen.value = false
  },
)

onMounted(async () => {
  window.addEventListener('scroll', onScroll, { passive: true })
  collection.load()
  await locales.load()
  try {
    const { data } = await api.get('/pages/nav')
    pages.value = data.data
  } catch {
    // sin menú de páginas: la nav base sigue funcionando
  }
})

onBeforeUnmount(() => window.removeEventListener('scroll', onScroll))
</script>

<template>
  <header class="site-header" :class="{ 'is-hidden': hidden }">
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

        <!-- Tres grupos con separador: [admin·descargas] | usuario | prefs -->
        <div class="site-header__actions">
          <span class="site-header__set">
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
          </span>

          <span class="site-header__sep" aria-hidden="true" />

          <!-- Entrar / usuario: SIEMPRE en la cabecera (patrón kontuan) -->
          <span class="site-header__set">
            <template v-if="auth.isAuthenticated">
              <RouterLink
                class="site-header__user"
                :to="{ name: 'account', params: { locale: locales.current } }"
                :title="t('nav.account')"
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
          </span>

          <span class="site-header__sep" aria-hidden="true" />

          <!-- Preferencias: idioma + tema -->
          <span class="site-header__set">
            <LocaleSelector
              :model-value="locales.current"
              :locales="locales.locales"
              @update:model-value="switchLocale"
            />
            <ThemeSelector />
          </span>
        </div>
      </div>
    </div>

    <!-- Línea 2 (escritorio): la barra de navegación, centrada -->
    <nav class="site-header__nav">
      <ul class="site-header__list">
        <li
          v-for="page in navPages"
          :key="page.id"
          class="site-header__item"
          :class="{
            'has-children': page.children.length,
            'is-suppressed': suppressedDropdown === page.id,
          }"
          @mouseleave="releaseDropdown(page.id)"
        >
          <RouterLink
            class="site-header__link"
            :class="{ 'is-active': isParentActive(page) }"
            :to="{ name: 'page', params: { locale: locales.current, slug: page.slug } }"
          >
            {{ page.label }}
            <ChevronDown v-if="page.children.length" :size="14" class="site-header__chevron" />
          </RouterLink>
          <!-- Submenú (hover / focus-within, patrón CDL): se cierra al elegir -->
          <ul v-if="page.children.length" class="site-header__dropdown">
            <li v-for="child in page.children" :key="child.id">
              <RouterLink
                class="site-header__dropdown-link"
                :to="{ name: 'page', params: { locale: locales.current, slug: child.slug } }"
                @click="closeDropdown(page.id)"
                >{{ child.label }}</RouterLink
              >
            </li>
          </ul>
        </li>
        <li v-for="section in navSections" :key="section.key" class="site-header__item">
          <RouterLink
            class="site-header__link"
            :to="{
              name: 'entity-index',
              params: { locale: locales.current, section: section.section },
            }"
            >{{ section.label }}</RouterLink
          >
        </li>
        <li class="site-header__item">
          <RouterLink
            class="site-header__link"
            :to="{ name: 'downloads', params: { locale: locales.current, dl: downloadsSegment } }"
            >{{ t('nav.downloads') }}</RouterLink
          >
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
        <li v-for="page in navPages" :key="page.id">
          <RouterLink
            class="site-header__link"
            :to="{ name: 'page', params: { locale: locales.current, slug: page.slug } }"
            >{{ page.label }}</RouterLink
          >
          <!-- Hijas: siempre desplegadas, con indentación (patrón CDL) -->
          <ul v-if="page.children.length" class="site-sidebar__children">
            <li v-for="child in page.children" :key="child.id">
              <RouterLink
                class="site-header__link"
                :to="{ name: 'page', params: { locale: locales.current, slug: child.slug } }"
                >{{ child.label }}</RouterLink
              >
            </li>
          </ul>
        </li>
        <li v-for="section in navSections" :key="section.key">
          <RouterLink
            class="site-header__link"
            :to="{
              name: 'entity-index',
              params: { locale: locales.current, section: section.section },
            }"
            >{{ section.label }}</RouterLink
          >
        </li>
        <li>
          <RouterLink
            class="site-header__link"
            :to="{ name: 'downloads', params: { locale: locales.current, dl: downloadsSegment } }"
          >
            {{ t('nav.downloads') }}
            <span v-if="collection.count" class="site-header__collection-count">
              {{ collection.count }}
            </span>
          </RouterLink>
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
