<script setup lang="ts">
import { onMounted } from 'vue'
import { Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, FilterBar, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BasePagination, BaseTabs } from '@edc-motor/ui'
import { useEntityList } from '@/composables/useEntityList'
import type { CardSubtype } from '@juego/shared'
import CardSubtypeFormModal from '@/components/card-subtypes/CardSubtypeFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'
import SortSelect from '@/components/SortSelect.vue'

// Taxonomía sin slug, sin publicación y sin single: CRUD por id y tabs
// todos/papelera. Solo nombre traducible.
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
} = useEntityList<CardSubtype>({
  resource: '/admin/card-subtypes',
  ns: 'cardSubtypes',
  resolveBy: 'id',
  tabKeys: ['all', 'trashed'],
  nameOf: (item) => item.name,
})

onMounted(init)
</script>

<template>
  <div class="card-subtypes">
    <div class="list-view__top">
      <BaseButton @click="openCreate">
        <template #icon><Plus :size="16" /></template>
        {{ t('cardSubtypes.newButton') }}
      </BaseButton>
    </div>

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
            t('cardSubtypes.state.trashed')
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

    <CardSubtypeFormModal v-model="formOpen" :mode="formMode" :target="formItem" @saved="onSaved" />

    <EntityPanel
      :item="selected"
      :name="selected ? tr(selected.name) : ''"
      :kicker="t('cardSubtypes.panelTitle')"
      :empty="t('cardSubtypes.panelEmpty')"
      :has-single="false"
      :has-publish="false"
      @edit="selected && edit(selected)"
      @del="selected && del(selected)"
      @restore="selected && restore(selected)"
      @force-delete="selected && forceDelete(selected)"
    />
  </div>
</template>
