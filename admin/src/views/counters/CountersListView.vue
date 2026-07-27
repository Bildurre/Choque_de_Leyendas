<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { FunnelX, Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BasePagination, BaseTabs, MultiSelect } from '@edc-motor/ui'
import { useEntityList } from '@/composables/useEntityList'
import { useIconsStore } from '@/stores/icons'
import type { Counter } from '@juego/shared'
import CounterFormModal from '@/components/counters/CounterFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'
import ListToolbar from '@/components/ListToolbar.vue'
import PanelSection from '@/components/PanelSection.vue'

// Sin single: se edita en modal y la API resuelve por id. Además de las tabs
// de estado, el listado filtra por tipo (boon|bane) con un multiselect en el
// panel derecho (el viejo usaba pestañas beneficio/perjuicio); el filtro
// viaja como `type[]=`.
const icons = useIconsStore()

const {
  t,
  items,
  loading,
  page,
  pages,
  status,
  search,
  sort,
  filters,
  clearFilters,
  tabs,
  tr,
  init,
  formOpen,
  formMode,
  formItem,
  openCreate,
  edit,
  onSaved,
  togglePublish,
  del,
  restore,
  forceDelete,
  regeneratePreview,
  selectedId,
  selected,
  select,
  hasPreview,
} = useEntityList<Counter>({
  resource: '/admin/counters',
  ns: 'counters',
  resolveBy: 'id',
  previewKey: 'counter',
  nameOf: (item) => item.name,
})

// Sin opción "Todos" en la lista: sin nada marcado, el placeholder del
// MultiSelect ya dice "Todos los tipos".
const typeOptions = computed(() => [
  { value: 'boon', label: t('counters.types.boon') },
  { value: 'bane', label: t('counters.types.bane') },
])

// Icono convencional del tipo (gestor de Iconos del motor); si no está
// subido, fallback visual con la inicial del tipo sobre color plano.
function typeIconUrl(type: string): string | null {
  return icons.icons.find((i) => i.slug === type)?.url ?? null
}

onMounted(async () => {
  await Promise.all([init(), icons.load()])
})
</script>

<template>
  <div class="counters">
    <div class="list-view__top">
      <BaseButton @click="openCreate">
        <template #icon><Plus :size="16" /></template>
        {{ t('counters.newButton') }}
      </BaseButton>
    </div>

    <!-- Barra del índice: búsqueda + orden (los filtros, en el panel derecho) -->
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
        <template #media>
          <!-- El tinte azul/rojo del tipo solo cuando NO hay imagen propia -->
          <div class="counter-art" :class="item.image ? undefined : `counter-art--${item.type}`">
            <img v-if="item.image" :src="item.image" alt="" />
            <img
              v-else-if="typeIconUrl(item.type)"
              :src="typeIconUrl(item.type) as string"
              alt=""
              class="counter-art__type-icon"
            />
            <span v-else class="counter-art__mono">{{
              t(`counters.types.${item.type}`).charAt(0)
            }}</span>
          </div>
        </template>

        <!-- Sin badge de estado (los tabs ya separan). Beneficio en azul
             ($info), perjuicio en rojo (mismo código que el panel) -->
        <template #badges>
          <span class="chip" :class="item.type === 'boon' ? 'is-info' : 'is-failed'">{{
            t(`counters.types.${item.type}`)
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

    <CounterFormModal v-model="formOpen" :mode="formMode" :target="formItem" @saved="onSaved" />

    <EntityPanel
      :item="selected"
      :name="selected ? tr(selected.name) : ''"
      :kicker="t('counters.panelTitle')"
      :empty="t('counters.panelEmpty')"
      :has-single="false"
      :has-preview="hasPreview"
      @deselect="selectedId = null"
      @edit="selected && edit(selected)"
      @toggle-publish="selected && togglePublish(selected)"
      @regenerate="selected && regeneratePreview(selected)"
      @del="selected && del(selected)"
      @restore="selected && restore(selected)"
      @force-delete="selected && forceDelete(selected)"
    >
      <!-- Filtros del listado: aplican en vivo (sin guardar), multivalor -->
      <template #filters>
        <MultiSelect
          v-model="filters.type"
          :label="t('counters.fields.type')"
          :placeholder="t('counters.filters.allTypes')"
          :options="typeOptions"
        />
        <BaseButton variant="text" type="button" @click="clearFilters">
          <template #icon><FunnelX :size="14" /></template>
          {{ t('common.filters.clear') }}
        </BaseButton>
      </template>

      <template #meta>
        <!-- El tipo a pelo (sin label): el tinte ya dice de qué va -->
        <p v-if="selected" class="manager-detail__meta">
          <span class="counters__type" :class="`counters__type--${selected.type}`">{{
            t(`counters.types.${selected.type}`)
          }}</span>
        </p>
        <!-- El efecto, en sección con el lenguaje común del panel -->
        <PanelSection
          v-if="selected && tr(selected.effect) !== '—'"
          :title="t('counters.fields.effect')"
        >
          <!-- eslint-disable vue/no-v-html -- wysiwyg saneado en servidor (DC-09) -->
          <div class="counters__effect" v-html="tr(selected.effect)"></div>
          <!-- eslint-enable vue/no-v-html -->
        </PanelSection>
      </template>
    </EntityPanel>
  </div>
</template>
