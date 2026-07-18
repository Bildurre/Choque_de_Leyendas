<script setup lang="ts">
import { onMounted } from 'vue'
import { Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BasePagination, BaseTabs } from '@edc-motor/ui'
import { useEntityList } from '@/composables/useEntityList'
import type { CardType } from '@juego/shared'
import CardTypeFormModal from '@/components/card-types/CardTypeFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'
import ListToolbar from '@/components/ListToolbar.vue'

// Taxonomía sin slug, sin publicación y sin single: CRUD por id y tabs
// todos/papelera. Los flags allows_subtypes/is_equipment se muestran como
// chips (sustituyen a los ids mágicos del viejo).
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
        <!-- Sin badge de estado (los tabs ya separan): la superclase (si la
             tiene) + los flags activos, en badges neutros -->
        <template #badges>
          <span v-if="item.hero_superclass" class="chip">{{ tr(item.hero_superclass.name) }}</span>
          <span v-if="item.allows_subtypes" class="chip">{{
            t('cardTypes.fields.allowsSubtypes')
          }}</span>
          <span v-if="item.is_equipment" class="chip">{{ t('cardTypes.fields.isEquipment') }}</span>
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
          {{ t('cardTypes.fields.heroSuperclass') }}:
          {{
            selected.hero_superclass
              ? tr(selected.hero_superclass.name)
              : t('cardTypes.noSuperclass')
          }}
        </p>
        <!-- Solo lo que SÍ tiene, en texto plano (sin chips ni colores de
             sí/no, regla transversal); lo que no tiene, no aparece -->
        <p v-if="selected?.allows_subtypes" class="manager-detail__meta">
          {{ t('cardTypes.fields.allowsSubtypes') }}
        </p>
        <p v-if="selected?.is_equipment" class="manager-detail__meta">
          {{ t('cardTypes.fields.isEquipment') }}
        </p>
      </template>
    </EntityPanel>
  </div>
</template>
