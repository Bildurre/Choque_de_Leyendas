<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, FilterBar, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BaseSelect, BaseTabs } from '@edc-motor/ui'
import { useEntityList } from '@/composables/useEntityList'
import { useIconsStore } from '@/stores/icons'
import type { Counter } from '@juego/shared'
import CounterFormModal from '@/components/counters/CounterFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'

// Sin single: se edita en modal y la API resuelve por id. Además de las tabs
// de estado, el listado filtra por tipo (boon|bane) con un select en la
// barra de búsqueda (el viejo usaba pestañas beneficio/perjuicio).
const icons = useIconsStore()
const typeFilter = ref('')

const {
  t,
  items,
  loading,
  status,
  search,
  tabs,
  tr,
  init,
  load,
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
  extraParams: () => ({ type: typeFilter.value || undefined }),
})

watch(typeFilter, () => load(1))

const typeOptions = computed(() => [
  { value: '', label: t('counters.filters.allTypes') },
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

    <!-- Búsqueda + filtro por tipo, por encima de las tabs (estilo kontuan) -->
    <FilterBar v-model="search" :placeholder="t('common.search')">
      <BaseSelect v-model="typeFilter" :options="typeOptions" />
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
        <template #media>
          <div class="counter-art" :class="`counter-art--${item.type}`">
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

        <template #badges>
          <span v-if="item.deleted_at" class="chip is-failed">{{
            t('counters.state.trashed')
          }}</span>
          <span v-else-if="item.is_published" class="chip is-ok">{{
            t('counters.state.published')
          }}</span>
          <span v-else class="chip">{{ t('counters.state.draft') }}</span>
          <span class="chip" :class="item.type === 'boon' ? 'is-ok' : 'is-failed'">{{
            t(`counters.types.${item.type}`)
          }}</span>
        </template>

        <template #meta>
          <span class="counters__effect">{{ tr(item.effect) }}</span>
        </template>
      </EntityCard>
    </BaseGrid>

    <CounterFormModal v-model="formOpen" :mode="formMode" :target="formItem" @saved="onSaved" />

    <EntityPanel
      :item="selected"
      :name="selected ? tr(selected.name) : ''"
      :kicker="t('counters.panelTitle')"
      :empty="t('counters.panelEmpty')"
      :has-single="false"
      :has-preview="hasPreview"
      @edit="selected && edit(selected)"
      @toggle-publish="selected && togglePublish(selected)"
      @regenerate="selected && regeneratePreview(selected)"
      @del="selected && del(selected)"
      @restore="selected && restore(selected)"
      @force-delete="selected && forceDelete(selected)"
    >
      <template #meta>
        <p v-if="selected" class="manager-detail__meta">
          <strong>{{ t('counters.fields.type') }}</strong>
          {{ t(`counters.types.${selected.type}`) }}
        </p>
        <p v-if="selected" class="manager-detail__meta">{{ tr(selected.effect) }}</p>
      </template>
    </EntityPanel>
  </div>
</template>
