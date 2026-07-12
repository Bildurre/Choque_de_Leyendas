<script setup lang="ts">
import { onMounted } from 'vue'
import { Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, FilterBar, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BaseTabs } from '@edc-motor/ui'
import { useEntityList } from '@/composables/useEntityList'
import type { EquipmentType } from '@juego/shared'
import EquipmentTypeFormModal from '@/components/equipment-types/EquipmentTypeFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'
import SortSelect from '@/components/SortSelect.vue'

// Taxonomía sin slug, sin publicación y sin single: CRUD por id. El filtro
// por categoría del viejo se porta como tabs extra (weapon|armor viajan en
// `status`; el controller las traduce a where('category', …)).
const {
  t,
  items,
  loading,
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
} = useEntityList<EquipmentType>({
  resource: '/admin/equipment-types',
  ns: 'equipmentTypes',
  resolveBy: 'id',
  tabKeys: ['all', 'weapon', 'armor', 'trashed'],
  nameOf: (item) => item.name,
})

onMounted(init)
</script>

<template>
  <div class="equipment-types">
    <div class="list-view__top">
      <BaseButton @click="openCreate">
        <template #icon><Plus :size="16" /></template>
        {{ t('equipmentTypes.newButton') }}
      </BaseButton>
    </div>

    <FilterBar v-model="search" :placeholder="t('common.search')">
      <SortSelect v-model="sort" />
    </FilterBar>
    <BaseTabs v-model="status" :tabs="tabs" />

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
            t('equipmentTypes.state.trashed')
          }}</span>
          <span class="chip" :class="`equipment-types__category--${item.category}`">{{
            t(`equipmentTypes.categories.${item.category}`)
          }}</span>
        </template>
      </EntityCard>
    </BaseGrid>

    <EquipmentTypeFormModal
      v-model="formOpen"
      :mode="formMode"
      :target="formItem"
      @saved="onSaved"
    />

    <EntityPanel
      :item="selected"
      :name="selected ? tr(selected.name) : ''"
      :kicker="t('equipmentTypes.panelTitle')"
      :empty="t('equipmentTypes.panelEmpty')"
      :has-single="false"
      :has-publish="false"
      @edit="selected && edit(selected)"
      @del="selected && del(selected)"
      @restore="selected && restore(selected)"
      @force-delete="selected && forceDelete(selected)"
    >
      <template #meta>
        <p v-if="selected" class="manager-detail__meta">
          {{ t('equipmentTypes.fields.category') }}:
          {{ t(`equipmentTypes.categories.${selected.category}`) }}
        </p>
      </template>
    </EntityPanel>
  </div>
</template>
