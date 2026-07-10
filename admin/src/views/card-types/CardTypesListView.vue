<script setup lang="ts">
import { onMounted } from 'vue'
import { Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, FilterBar, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BaseTabs } from '@edc-motor/ui'
import { useEntityList } from '@/composables/useEntityList'
import type { CardType } from '@juego/shared'
import CardTypeFormModal from '@/components/card-types/CardTypeFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'

// Taxonomía sin slug, sin publicación y sin single: CRUD por id y tabs
// todos/papelera. Los flags allows_subtypes/is_equipment se muestran como
// chips (sustituyen a los ids mágicos del viejo).
const {
  t,
  items,
  loading,
  status,
  search,
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
} = useEntityList<CardType>({
  resource: '/admin/card-types',
  ns: 'cardTypes',
  resolveBy: 'id',
  tabKeys: ['all', 'trashed'],
  nameOf: (item) => item.name,
})

onMounted(init)
</script>

<template>
  <div class="card-types">
    <div class="list-view__top">
      <BaseButton @click="openCreate">
        <template #icon><Plus :size="16" /></template>
        {{ t('cardTypes.newButton') }}
      </BaseButton>
    </div>

    <FilterBar v-model="search" :placeholder="t('common.search')" />
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
        @view="select(item)"
      >
        <template #badges>
          <span v-if="item.deleted_at" class="chip is-failed">{{
            t('cardTypes.state.trashed')
          }}</span>
          <span v-if="item.allows_subtypes" class="chip is-ok">{{
            t('cardTypes.fields.allowsSubtypes')
          }}</span>
          <span v-if="item.is_equipment" class="chip is-ok">{{
            t('cardTypes.fields.isEquipment')
          }}</span>
        </template>

        <template #meta>
          <span>{{
            item.hero_superclass ? tr(item.hero_superclass.name) : t('cardTypes.noSuperclass')
          }}</span>
        </template>
      </EntityCard>
    </BaseGrid>

    <CardTypeFormModal v-model="formOpen" :mode="formMode" :target="formItem" @saved="onSaved" />

    <EntityPanel
      :item="selected"
      :name="selected ? tr(selected.name) : ''"
      :kicker="t('cardTypes.panelTitle')"
      :empty="t('cardTypes.panelEmpty')"
      :has-single="false"
      :has-publish="false"
      @edit="selected && edit(selected)"
      @del="selected && del(selected)"
      @restore="selected && restore(selected)"
      @force-delete="selected && forceDelete(selected)"
    >
      <template #meta>
        <p v-if="selected" class="manager-detail__meta">
          {{
            selected.hero_superclass
              ? tr(selected.hero_superclass.name)
              : t('cardTypes.noSuperclass')
          }}
        </p>
        <p v-if="selected" class="manager-detail__meta card-types__flags">
          <span :class="['chip', selected.allows_subtypes ? 'is-ok' : 'is-missing']">{{
            t('cardTypes.fields.allowsSubtypes')
          }}</span>
          <span :class="['chip', selected.is_equipment ? 'is-ok' : 'is-missing']">{{
            t('cardTypes.fields.isEquipment')
          }}</span>
        </p>
      </template>
    </EntityPanel>
  </div>
</template>
