<script setup lang="ts">
import { onMounted } from 'vue'
import { Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BasePagination, BaseTabs } from '@edc-motor/ui'
import { useEntityList } from '@/composables/useEntityList'
import type { HeroClass } from '@juego/shared'
import HeroClassFormModal from '@/components/hero-classes/HeroClassFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'
import ListToolbar from '@/components/ListToolbar.vue'

// Taxonomía con superclase y pasiva: tabs solo all/trashed, sin single ni
// publicación; la API resuelve por id.
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

    <!-- Barra del índice: búsqueda + toggles de ordenación -->
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
        <!-- Sin badge de estado (los tabs ya separan): la superclase, en badge -->
        <template #badges>
          <span v-if="item.hero_superclass" class="chip">{{ tr(item.hero_superclass.name) }}</span>
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
        <!-- Término femenino (si existe), con su etiqueta; texto plano -->
        <p v-if="selected && tr(selected.name_female) !== '—'" class="manager-detail__meta">
          {{ t('heroClasses.fields.nameFemale') }}: {{ tr(selected.name_female) }}
        </p>
        <!-- Pasiva de la clase, en sección propia -->
        <template v-if="selected && tr(selected.passive) !== '—'">
          <h4 class="hero-classes__panel-title">{{ t('heroClasses.fields.passive') }}</h4>
          <!-- eslint-disable vue/no-v-html -- HTML del wysiwyg, saneado en servidor (DC-09) -->
          <div class="rich-content hero-classes__passive" v-html="tr(selected.passive)"></div>
        </template>
      </template>
    </EntityPanel>
  </div>
</template>
