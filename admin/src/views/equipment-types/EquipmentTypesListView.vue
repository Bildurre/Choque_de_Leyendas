<script setup lang="ts">
import { onMounted } from 'vue'
import { Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BasePagination, BaseTabs } from '@edc-motor/ui'
import { useEntityList } from '@/composables/useEntityList'
import type { EquipmentType } from '@juego/shared'
import EquipmentTypeFormModal from '@/components/equipment-types/EquipmentTypeFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'
import ListToolbar from '@/components/ListToolbar.vue'
import PanelSection from '@/components/PanelSection.vue'

// Taxonomía sin slug, sin publicación y sin single: CRUD por id. El chip
// marca los tipos que llevan manos (armas): sus cartas exigen el campo manos.
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
} = useEntityList<EquipmentType>({
  resource: '/admin/equipment-types',
  ns: 'equipmentTypes',
  resolveBy: 'id',
  tabKeys: ['all', 'trashed'],
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
        <!-- Sin badge de estado (los tabs ya separan): solo la marca de manos -->
        <template #badges>
          <span v-if="item.uses_hands" class="chip equipment-types__hands">{{
            t('equipmentTypes.fields.usesHands')
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
        <!-- Solo si lleva manos (si no lleva, no aparece nada de manos) -->
        <p v-if="selected?.uses_hands" class="manager-detail__meta">
          {{ t('equipmentTypes.fields.usesHands') }}
        </p>
        <!-- Cantidades como ENLACES a cada index filtrado por este tipo -->
        <PanelSection v-if="selected" :title="t('common.sections.collection')">
          <ul class="panel-counts">
            <li>
              <RouterLink
                class="hero-link"
                :to="{
                  name: 'equipment-subtypes',
                  query: { equipment_type_id: String(selected.id) },
                }"
                ><strong>{{ t('equipmentTypes.counts.subtypes') }}</strong></RouterLink
              ><span>{{ selected.subtypes_count ?? 0 }}</span>
            </li>
            <li>
              <RouterLink
                class="hero-link"
                :to="{ name: 'cards', query: { equipment_type_id: String(selected.id) } }"
                ><strong>{{ t('equipmentTypes.counts.cards') }}</strong></RouterLink
              ><span>{{ selected.cards_count ?? 0 }}</span>
            </li>
          </ul>
        </PanelSection>
      </template>
    </EntityPanel>
  </div>
</template>
