<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  Axe,
  Coins,
  DatabaseBackup,
  Dices,
  FileText,
  Flag,
  Globe,
  GraduationCap,
  LayoutDashboard,
  Layers,
  Images,
  Settings,
  Shapes,
  Shield,
  Sparkles,
  SlidersHorizontal,
  Swords,
  Tags,
  Target,
  Users,
  WalletCards,
  Zap,
  LogOut,
} from '@lucide/vue'
import { AdminLayout } from '@edc-motor/admin-kit'
import { ToastContainer, ConfirmDialog, type Crumb } from '@edc-motor/ui'
import { useAuthStore } from '@/stores/auth'
import { useLocalesStore } from '@/stores/locales'
import { usePageCrumb } from '@/composables/usePageCrumb'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const auth = useAuthStore()
const locales = useLocalesStore()

const isAdminArea = computed(() => route.meta.admin === true)
const title = computed(() => (route.meta.titleKey ? t(route.meta.titleKey as string) : ''))
const initial = computed(() => auth.user?.name?.charAt(0)?.toUpperCase() ?? '?')

const homeCrumb = computed<Crumb>(() => ({
  label: t('breadcrumbs.home'),
  to: { name: 'dashboard' },
}))
const { tail } = usePageCrumb()
const crumbs = computed<Crumb[]>(() => {
  const list = (route.meta.breadcrumbs as { key: string; to?: string }[] | undefined) ?? []
  const mapped: Crumb[] = list.map((c) => ({
    label: t(`breadcrumbs.${c.key}`),
    to: c.to ? { name: c.to } : undefined,
  }))
  // Tramo dinámico (el nombre del single), fijado por la vista.
  if (tail.value) mapped.push({ label: tail.value })
  return mapped
})

// Carga los locales cuando entramos al área de admin (para el selector).
// El store deduplica peticiones en vuelo; si falla, los selectores quedan
// vacíos y cada vista ya avisa de sus propios errores de carga.
watch(
  isAdminArea,
  (inAdmin) => {
    if (inAdmin) locales.load().catch(() => {})
  },
  { immediate: true },
)

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}

// Enlace a la web pública (espejo del enlace a admin del header público).
const appUrl = (import.meta.env.VITE_APP_URL as string | undefined) || 'http://localhost:5173'

/**
 * Ir a la web MANTENIENDO la sesión: código de traspaso de un solo uso que
 * la web canjea al cargar. Si falla, se navega igual (como invitado).
 */
async function goToSite(event: MouseEvent) {
  event.preventDefault()
  let url = appUrl
  try {
    const code = await auth.requestHandoff()
    url = `${appUrl}/?handoff=${code}`
  } catch {
    // sin código: se entra como invitado
  }
  window.location.href = url
}

// Resalta el ítem del menú también en las vistas hijas (single → su lista):
// cada ruta declara su sección en meta.nav y aquí se aplica la clase `active`
// (mismo estilo que router-link-active, que solo cubre la lista).
function navActive(section: string) {
  return { active: route.meta.nav === section }
}
</script>

<template>
  <AdminLayout
    v-if="isAdminArea"
    :title="title"
    brand="EdC Admin"
    :locales="locales.locales"
    :locale="locales.current"
    :home-crumb="homeCrumb"
    :breadcrumbs="crumbs"
    @update:locale="locales.setCurrent"
  >
    <!-- Menú agrupado: inicio | juego | generados | web | sistema -->
    <template #nav>
      <RouterLink class="nav-item" :class="navActive('dashboard')" :to="{ name: 'dashboard' }">
        <LayoutDashboard class="nav-icon" :size="20" /><span class="nav-label">{{
          t('nav.dashboard')
        }}</span>
      </RouterLink>

      <!-- Entidades del juego: contenido troncal -->
      <hr v-if="auth.can('manage-game')" class="sidebar-divider" />
      <RouterLink
        v-if="auth.can('manage-game')"
        class="nav-item"
        :class="navActive('factions')"
        :to="{ name: 'factions' }"
      >
        <Flag class="nav-icon" :size="20" /><span class="nav-label">{{ t('nav.factions') }}</span>
      </RouterLink>
      <RouterLink
        v-if="auth.can('manage-game')"
        class="nav-item"
        :class="navActive('heroes')"
        :to="{ name: 'heroes' }"
      >
        <Swords class="nav-icon" :size="20" /><span class="nav-label">{{ t('nav.heroes') }}</span>
      </RouterLink>
      <RouterLink
        v-if="auth.can('manage-game')"
        class="nav-item"
        :class="navActive('cards')"
        :to="{ name: 'cards' }"
      >
        <WalletCards class="nav-icon" :size="20" /><span class="nav-label">{{
          t('nav.cards')
        }}</span>
      </RouterLink>
      <RouterLink
        v-if="auth.can('manage-game')"
        class="nav-item"
        :class="navActive('factionDecks')"
        :to="{ name: 'faction-decks' }"
      >
        <Layers class="nav-icon" :size="20" /><span class="nav-label">{{
          t('nav.factionDecks')
        }}</span>
      </RouterLink>
      <RouterLink
        v-if="auth.can('manage-game')"
        class="nav-item"
        :class="navActive('counters')"
        :to="{ name: 'counters' }"
      >
        <Coins class="nav-icon" :size="20" /><span class="nav-label">{{ t('nav.counters') }}</span>
      </RouterLink>
      <RouterLink
        v-if="auth.can('manage-game')"
        class="nav-item"
        :class="navActive('heroAbilities')"
        :to="{ name: 'hero-abilities' }"
      >
        <Sparkles class="nav-icon" :size="20" /><span class="nav-label">{{
          t('nav.heroAbilities')
        }}</span>
      </RouterLink>

      <!-- Taxonomías y configuraciones del juego -->
      <hr v-if="auth.can('manage-game')" class="sidebar-divider" />
      <RouterLink
        v-if="auth.can('manage-game')"
        class="nav-item"
        :class="navActive('heroSuperclasses')"
        :to="{ name: 'hero-superclasses' }"
      >
        <Shield class="nav-icon" :size="20" /><span class="nav-label">{{
          t('nav.heroSuperclasses')
        }}</span>
      </RouterLink>
      <RouterLink
        v-if="auth.can('manage-game')"
        class="nav-item"
        :class="navActive('heroClasses')"
        :to="{ name: 'hero-classes' }"
      >
        <GraduationCap class="nav-icon" :size="20" /><span class="nav-label">{{
          t('nav.heroClasses')
        }}</span>
      </RouterLink>
      <RouterLink
        v-if="auth.can('manage-game')"
        class="nav-item"
        :class="navActive('heroRaces')"
        :to="{ name: 'hero-races' }"
      >
        <Users class="nav-icon" :size="20" /><span class="nav-label">{{ t('nav.heroRaces') }}</span>
      </RouterLink>
      <RouterLink
        v-if="auth.can('manage-game')"
        class="nav-item"
        :class="navActive('attackRanges')"
        :to="{ name: 'attack-ranges' }"
      >
        <Target class="nav-icon" :size="20" /><span class="nav-label">{{
          t('nav.attackRanges')
        }}</span>
      </RouterLink>
      <RouterLink
        v-if="auth.can('manage-game')"
        class="nav-item"
        :class="navActive('attackSubtypes')"
        :to="{ name: 'attack-subtypes' }"
      >
        <Zap class="nav-icon" :size="20" /><span class="nav-label">{{
          t('nav.attackSubtypes')
        }}</span>
      </RouterLink>
      <RouterLink
        v-if="auth.can('manage-game')"
        class="nav-item"
        :class="navActive('cardTypes')"
        :to="{ name: 'card-types' }"
      >
        <Layers class="nav-icon" :size="20" /><span class="nav-label">{{
          t('nav.cardTypes')
        }}</span>
      </RouterLink>
      <RouterLink
        v-if="auth.can('manage-game')"
        class="nav-item"
        :class="navActive('cardSubtypes')"
        :to="{ name: 'card-subtypes' }"
      >
        <Tags class="nav-icon" :size="20" /><span class="nav-label">{{
          t('nav.cardSubtypes')
        }}</span>
      </RouterLink>
      <RouterLink
        v-if="auth.can('manage-game')"
        class="nav-item"
        :class="navActive('equipmentTypes')"
        :to="{ name: 'equipment-types' }"
      >
        <Swords class="nav-icon" :size="20" /><span class="nav-label">{{
          t('nav.equipmentTypes')
        }}</span>
      </RouterLink>
      <RouterLink
        v-if="auth.can('manage-game')"
        class="nav-item"
        :class="navActive('equipmentSubtypes')"
        :to="{ name: 'equipment-subtypes' }"
      >
        <Axe class="nav-icon" :size="20" /><span class="nav-label">{{
          t('nav.equipmentSubtypes')
        }}</span>
      </RouterLink>
      <RouterLink
        v-if="auth.can('manage-game')"
        class="nav-item"
        :class="navActive('gameModes')"
        :to="{ name: 'game-modes' }"
      >
        <Dices class="nav-icon" :size="20" /><span class="nav-label">{{ t('nav.gameModes') }}</span>
      </RouterLink>
      <RouterLink
        v-if="auth.can('manage-game')"
        class="nav-item"
        :class="navActive('heroAttributesConfig')"
        :to="{ name: 'hero-attributes-configuration' }"
      >
        <SlidersHorizontal class="nav-icon" :size="20" /><span class="nav-label">{{
          t('nav.heroAttributesConfig')
        }}</span>
      </RouterLink>
      <RouterLink
        v-if="auth.can('manage-game')"
        class="nav-item"
        :class="navActive('deckAttributesConfigs')"
        :to="{ name: 'deck-attributes-configurations' }"
      >
        <SlidersHorizontal class="nav-icon" :size="20" /><span class="nav-label">{{
          t('nav.deckAttributesConfigs')
        }}</span>
      </RouterLink>
      <RouterLink
        v-if="auth.can('manage-game')"
        class="nav-item"
        :class="navActive('icons')"
        :to="{ name: 'icons' }"
      >
        <Shapes class="nav-icon" :size="20" /><span class="nav-label">{{ t('nav.icons') }}</span>
      </RouterLink>

      <!-- Generados: imágenes PNG y PDF -->
      <hr v-if="auth.can('manage-game')" class="sidebar-divider" />
      <RouterLink
        v-if="auth.can('manage-game')"
        class="nav-item"
        :class="navActive('previews')"
        :to="{ name: 'previews' }"
      >
        <Images class="nav-icon" :size="20" /><span class="nav-label">{{ t('nav.previews') }}</span>
      </RouterLink>
      <RouterLink
        v-if="auth.can('manage-game')"
        class="nav-item"
        :class="navActive('pdfs')"
        :to="{ name: 'pdfs' }"
      >
        <FileText class="nav-icon" :size="20" /><span class="nav-label">{{ t('nav.pdfs') }}</span>
      </RouterLink>

      <!-- La web: CRM y configuración -->
      <hr v-if="auth.can('manage-web')" class="sidebar-divider" />
      <RouterLink
        v-if="auth.can('manage-web')"
        class="nav-item"
        :class="navActive('pages')"
        :to="{ name: 'pages' }"
      >
        <FileText class="nav-icon" :size="20" /><span class="nav-label">{{ t('nav.pages') }}</span>
      </RouterLink>
      <RouterLink
        v-if="auth.can('manage-web')"
        class="nav-item"
        :class="navActive('settings')"
        :to="{ name: 'settings' }"
      >
        <Settings class="nav-icon" :size="20" /><span class="nav-label">{{
          t('nav.settings')
        }}</span>
      </RouterLink>

      <!-- Sistema: copias y usuarios -->
      <hr v-if="auth.can('manage-web') || auth.can('manage-users')" class="sidebar-divider" />
      <RouterLink
        v-if="auth.can('manage-web')"
        class="nav-item"
        :class="navActive('backups')"
        :to="{ name: 'backups' }"
      >
        <DatabaseBackup class="nav-icon" :size="20" /><span class="nav-label">{{
          t('nav.backups')
        }}</span>
      </RouterLink>
      <RouterLink
        v-if="auth.can('manage-users')"
        class="nav-item"
        :class="navActive('users')"
        :to="{ name: 'users' }"
      >
        <Users class="nav-icon" :size="20" /><span class="nav-label">{{ t('nav.users') }}</span>
      </RouterLink>
    </template>

    <!-- Barra superior: ir a la web pública (solo icono, con traspaso) -->
    <template #actions>
      <a class="navbar-viewsite" :href="appUrl" :title="t('nav.viewSite')" @click="goToSite">
        <Globe :size="18" />
      </a>
    </template>

    <template #user="{ collapsed }">
      <div class="who">
        <span class="who__avatar">{{ initial }}</span>
        <span v-if="!collapsed" class="who__name">{{ auth.user?.name }}</span>
      </div>
      <button
        v-if="!collapsed"
        class="who-logout"
        type="button"
        :title="t('common.logout')"
        @click="logout"
      >
        <LogOut :size="20" />
      </button>
    </template>

    <RouterView />
  </AdminLayout>
  <RouterView v-else />

  <ToastContainer />
  <ConfirmDialog />
</template>
