<script setup lang="ts">
import { onMounted } from 'vue'
import { Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, FilterBar, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BaseTabs } from '@edc-motor/ui'
import { useEntityList } from '@/composables/useEntityList'
import type { HeroClass } from '@juego/shared'
import HeroClassFormModal from '@/components/hero-classes/HeroClassFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'
import SortSelect from '@/components/SortSelect.vue'

// Taxonomía con superclase y pasiva: tabs solo all/trashed, sin single ni
// publicación; la API resuelve por id.
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
} = useEntityList<HeroClass>({
  resource: '/admin/hero-classes',
  ns: 'heroClasses',
  nameOf: (item) => item.name,
  resolveBy: 'id',
  tabKeys: ['all', 'trashed'],
})

onMounted(init)
</script>

<template>
  <div class="hero-classes">
    <div class="list-view__top">
      <BaseButton @click="openCreate">
        <template #icon><Plus :size="16" /></template>
        {{ t('heroClasses.newButton') }}
      </BaseButton>
    </div>

    <!-- Filtros por encima de las tabs (estilo kontuan) -->
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
            t('heroClasses.state.trashed')
          }}</span>
        </template>

        <template #meta>
          <span>{{ item.hero_superclass ? tr(item.hero_superclass.name) : '—' }}</span>
        </template>
      </EntityCard>
    </BaseGrid>

    <HeroClassFormModal v-model="formOpen" :mode="formMode" :target="formItem" @saved="onSaved" />

    <EntityPanel
      :item="selected"
      :name="selected ? tr(selected.name) : ''"
      :kicker="t('heroClasses.panelTitle')"
      :empty="t('heroClasses.panelEmpty')"
      :has-single="false"
      :has-publish="false"
      @edit="selected && edit(selected)"
      @del="selected && del(selected)"
      @restore="selected && restore(selected)"
      @force-delete="selected && forceDelete(selected)"
    >
      <template #meta>
        <p v-if="selected" class="manager-detail__meta">
          {{ t('heroClasses.fields.superclass') }}:
          {{ selected.hero_superclass ? tr(selected.hero_superclass.name) : '—' }}
        </p>
        <!-- eslint-disable vue/no-v-html -- HTML del wysiwyg, saneado en servidor (DC-09) -->
        <div
          v-if="selected && selected.passive"
          class="hero-classes__passive"
          v-html="tr(selected.passive) !== '—' ? tr(selected.passive) : ''"
        ></div>
      </template>
    </EntityPanel>
  </div>
</template>
