<script setup lang="ts">
import { onMounted } from 'vue'
import { Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, FilterBar, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BasePagination, BaseTabs } from '@edc-motor/ui'
import { useEntityList } from '@/composables/useEntityList'
import type { HeroRace } from '@juego/shared'
import HeroRaceFormModal from '@/components/hero-races/HeroRaceFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'
import SortSelect from '@/components/SortSelect.vue'

// Taxonomía simple: tabs solo all/trashed, sin single ni publicación;
// la API resuelve por id.
const {
  t,
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
} = useEntityList<HeroRace>({
  resource: '/admin/hero-races',
  ns: 'heroRaces',
  nameOf: (item) => item.name,
  resolveBy: 'id',
  tabKeys: ['all', 'trashed'],
})

onMounted(init)
</script>

<template>
  <div class="hero-races">
    <div class="list-view__top">
      <BaseButton @click="openCreate">
        <template #icon><Plus :size="16" /></template>
        {{ t('heroRaces.newButton') }}
      </BaseButton>
    </div>

    <!-- Filtros por encima de las tabs (estilo kontuan) -->
    <FilterBar v-model="search" :placeholder="t('common.search')">
      <SortSelect v-model="sort" />
    </FilterBar>
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
          <span v-if="item.deleted_at" class="chip is-failed">{{
            t('heroRaces.state.trashed')
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

    <HeroRaceFormModal v-model="formOpen" :mode="formMode" :target="formItem" @saved="onSaved" />

    <EntityPanel
      :item="selected"
      :name="selected ? tr(selected.name) : ''"
      :kicker="t('heroRaces.panelTitle')"
      :empty="t('heroRaces.panelEmpty')"
      :has-single="false"
      :has-publish="false"
      @edit="selected && edit(selected)"
      @del="selected && del(selected)"
      @restore="selected && restore(selected)"
      @force-delete="selected && forceDelete(selected)"
    />
  </div>
</template>
