<script setup lang="ts">
import { onMounted } from 'vue'
import { Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BasePagination, BaseTabs } from '@edc-motor/ui'
import { useEntityList } from '@/composables/useEntityList'
import type { GameMode, SingleLinkRef } from '@juego/shared'
import GameModeFormModal from '@/components/game-modes/GameModeFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'
import ListToolbar from '@/components/ListToolbar.vue'
import PanelSection from '@/components/PanelSection.vue'

// Taxonomía sin slug, sin publicación y sin single: CRUD por id y tabs
// todos/papelera. Nombre + descripción traducibles.
const {
  t,
  locales,
  items,
  loading,
  page,
  pages,
  status,
  search,
  sort,
  tabs,
  tr,
  init,
  formOpen,
  formMode,
  formItem,
  openCreate,
  edit,
  onSaved,
  del,
  restore,
  forceDelete,
  selectedId,
  selected,
  select,
} = useEntityList<GameMode>({
  resource: '/admin/game-modes',
  ns: 'gameModes',
  resolveBy: 'id',
  tabKeys: ['all', 'trashed'],
  nameOf: (item) => item.name,
})

/** Slug localizado de un mazo embebido (enlace a su single). */
function slugOf(item: SingleLinkRef): string {
  return item.slug?.[locales.current] || Object.values(item.slug ?? {})[0] || ''
}

onMounted(init)
</script>

<template>
  <div class="game-modes">
    <div class="list-view__top">
      <BaseButton @click="openCreate">
        <template #icon><Plus :size="16" /></template>
        {{ t('gameModes.newButton') }}
      </BaseButton>
    </div>

    <ListToolbar v-model="search" v-model:sort="sort" />
    <BaseTabs v-model="status" :tabs="tabs" />
    <BasePagination
      v-model:page="page"
      :pages="pages"
      class="list-view__pagination"
      :prev-label="t('common.pagination.prev')"
      :next-label="t('common.pagination.next')"
      :of-label="t('common.pagination.of', { page, pages })"
    />

    <EmptyState v-if="!loading && !items.length" :title="t('common.empty')" />

    <BaseGrid v-else preset="cards" gap="md">
      <EntityCard
        v-for="item in items"
        :key="item.id"
        :title="tr(item.name)"
        :muted="!!item.deleted_at"
        :active="selectedId === item.id"
        clickable
        :editable="!item.deleted_at"
        :edit-label="t('common.actions.edit')"
        @view="select(item)"
        @edit="edit(item)"
      >
        <template #badges>
          <span v-if="item.is_default" class="chip is-success">{{
            t('gameModes.badge.default')
          }}</span>
          <span v-if="item.deleted_at" class="chip is-failed">{{
            t('gameModes.state.trashed')
          }}</span>
        </template>
      </EntityCard>
    </BaseGrid>

    <BasePagination
      v-model:page="page"
      :pages="pages"
      class="list-view__pagination list-view__pagination--bottom"
      :prev-label="t('common.pagination.prev')"
      :next-label="t('common.pagination.next')"
      :of-label="t('common.pagination.of', { page, pages })"
    />

    <GameModeFormModal v-model="formOpen" :mode="formMode" :target="formItem" @saved="onSaved" />

    <EntityPanel
      :item="selected"
      :name="selected ? tr(selected.name) : ''"
      :kicker="t('gameModes.panelTitle')"
      :empty="t('gameModes.panelEmpty')"
      :has-single="false"
      :has-publish="false"
      @edit="selected && edit(selected)"
      @del="selected && del(selected)"
      @restore="selected && restore(selected)"
      @force-delete="selected && forceDelete(selected)"
    >
      <template #meta>
        <p v-if="selected && tr(selected.description) !== '—'" class="manager-detail__meta">
          {{ tr(selected.description) }}
        </p>
        <!-- Por defecto solo si LO ES (sin chips ni sí/no en paneles) -->
        <p v-if="selected?.is_default" class="manager-detail__meta">
          {{ t('gameModes.fields.isDefault') }}
        </p>
        <!-- Configuración de mazos del modo, en su sección -->
        <PanelSection v-if="selected" :title="t('gameModes.sections.config')">
          <dl class="game-modes__facts">
            <div>
              <dt>{{ t('gameModes.fields.minCards') }}</dt>
              <dd>{{ selected.min_cards }}</dd>
            </div>
            <div>
              <dt>{{ t('gameModes.fields.maxCards') }}</dt>
              <dd>{{ selected.max_cards }}</dd>
            </div>
            <div>
              <dt>{{ t('gameModes.fields.maxCopiesPerCard') }}</dt>
              <dd>{{ selected.max_copies_per_card }}</dd>
            </div>
            <div>
              <dt>{{ t('gameModes.fields.requiredHeroes') }}</dt>
              <dd>{{ selected.required_heroes }}</dd>
            </div>
          </dl>
        </PanelSection>
        <!-- Los mazos del modo, cada uno enlazado a su single por el slug
             del locale activo -->
        <PanelSection
          v-if="selected"
          :title="`${t('gameModes.counts.decks')} (${selected.faction_decks_count ?? selected.faction_decks?.length ?? 0})`"
        >
          <ul class="panel-counts">
            <li v-for="deck in selected.faction_decks ?? []" :key="deck.id">
              <RouterLink
                class="hero-link"
                :to="{ name: 'faction-deck-single', params: { slug: slugOf(deck) } }"
                >{{ tr(deck.name) }}</RouterLink
              >
            </li>
          </ul>
        </PanelSection>
      </template>
    </EntityPanel>
  </div>
</template>
