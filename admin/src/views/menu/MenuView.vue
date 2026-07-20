<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { MenuManager, type MenuManagerLabels } from '@edc-motor/admin-kit'
import { api } from '@/lib/api'
import { useLocalesStore } from '@/stores/locales'

// Configurador del menú de la web pública (doc 10 ampliado): mezcla páginas
// del CRM y las rutas propias de CdL (motor.menu.routes, config del api:
// cards/heroes/factions/decks — los índices de entidades —, life-counter y
// dice-roller — las herramientas con ruta pública propia — y downloads).
// `routeLabels` es la etiqueta visible de cada route_key: debe casar con el
// mapa de AppHeader.vue (app).
const { t } = useI18n()
const locales = useLocalesStore()

const routeLabels = computed<Record<string, string>>(() => ({
  cards: t('nav.cards'),
  heroes: t('nav.heroes'),
  factions: t('nav.factions'),
  decks: t('nav.factionDecks'),
  'life-counter': t('menu.routes.lifeCounter'),
  'dice-roller': t('menu.routes.diceRoller'),
  downloads: t('menu.routes.downloads'),
}))

const labels = computed<Partial<MenuManagerLabels>>(() => ({
  newGroup: t('menu.newGroup'),
  newGroupTitle: t('menu.newGroupTitle'),
  editGroupTitle: t('menu.editGroupTitle'),
  groupLabel: t('menu.groupLabel'),
  save: t('common.save'),
  cancel: t('common.cancel'),
  delete: t('common.actions.delete'),
  confirmDelete: t('menu.confirmDelete'),
  empty: t('menu.empty'),
  root: t('menu.root'),
  hidden: t('menu.hidden'),
  draft: t('pages.draft'),
  moveUp: t('pages.moveUp'),
  moveDown: t('pages.moveDown'),
  visible: t('menu.visible'),
  group: t('menu.group'),
  error: t('common.errors.action'),
}))
</script>

<template>
  <div class="menu-view">
    <h1 class="single__title">{{ t('menu.title') }}</h1>
    <p class="menu-view__hint">{{ t('menu.hint') }}</p>

    <MenuManager
      :api="api"
      :locales="locales.locales"
      :route-labels="routeLabels"
      :labels="labels"
    />
  </div>
</template>
