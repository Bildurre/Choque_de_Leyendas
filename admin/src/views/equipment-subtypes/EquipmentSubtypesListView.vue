<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BasePagination, BaseSelect, BaseTabs } from '@edc-motor/ui'
import { useEntityList } from '@/composables/useEntityList'
import { api } from '@/lib/api'
import type { EquipmentSubtype, EquipmentTypeOption } from '@juego/shared'
import EquipmentSubtypeFormModal from '@/components/equipment-subtypes/EquipmentSubtypeFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'
import ListToolbar from '@/components/ListToolbar.vue'

// Taxonomía sin slug, sin publicación y sin single: CRUD por id. Cada
// subtipo pertenece a un tipo de equipo; el listado filtra por tipo con un
// select en el panel derecho (slot `filters` del EntityPanel).
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
} = useEntityList<EquipmentSubtype>({
  resource: '/admin/equipment-subtypes',
  ns: 'equipmentSubtypes',
  resolveBy: 'id',
  tabKeys: ['all', 'trashed'],
  nameOf: (item) => item.name,
})

// Opciones del select de filtro por tipo de equipo (endpoint options).
const equipmentTypes = ref<EquipmentTypeOption[]>([])

const typeOptions = computed(() => [
  { value: '', label: t('equipmentSubtypes.filters.allTypes') },
  ...equipmentTypes.value.map((o) => ({ value: String(o.id), label: tr(o.name) })),
])

// Paleta del juego (tokens del admin: $danger, $info, $warning, $success +
// dos acentos más) para teñir el badge del tipo de equipo. Criterio: color
// ESTABLE y determinista por id — paleta[(id - 1) % paleta.length] — así
// Arma (id 1) es siempre roja, Armadura (id 2) azul, etc., y cada tipo nuevo
// recibe el siguiente color de la rueda sin repetir hasta agotarla.
const TYPE_PALETTE = ['#ff6b6b', '#6c8cff', '#fbbf24', '#4ade80', '#c084fc', '#2dd4bf']

function typeColor(typeId: number): string {
  return TYPE_PALETTE[(typeId - 1) % TYPE_PALETTE.length]
}

async function loadFilterOptions() {
  try {
    const { data } = await api.get('/admin/equipment-types/options')
    equipmentTypes.value = data.data
  } catch {
    // Sin opciones no hay filtro, pero el listado sigue funcionando.
  }
}

onMounted(async () => {
  await Promise.all([init(), loadFilterOptions()])
})
</script>

<template>
  <div class="equipment-subtypes">
    <div class="list-view__top">
      <BaseButton @click="openCreate">
        <template #icon><Plus :size="16" /></template>
        {{ t('equipmentSubtypes.newButton') }}
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
        <!-- Sin badge de estado (los tabs ya separan): el tipo de equipo,
             teñido con su color estable de la paleta (determinista por id) -->
        <template #badges>
          <span
            v-if="item.equipment_type"
            class="chip"
            :style="{ color: typeColor(item.equipment_type_id) }"
            >{{ tr(item.equipment_type.name) }}</span
          >
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

    <EquipmentSubtypeFormModal
      v-model="formOpen"
      :mode="formMode"
      :target="formItem"
      @saved="onSaved"
    />

    <EntityPanel
      :item="selected"
      :name="selected ? tr(selected.name) : ''"
      :kicker="t('equipmentSubtypes.panelTitle')"
      :empty="t('equipmentSubtypes.panelEmpty')"
      :has-single="false"
      :has-publish="false"
      @edit="selected && edit(selected)"
      @del="selected && del(selected)"
      @restore="selected && restore(selected)"
      @force-delete="selected && forceDelete(selected)"
    >
      <!-- Filtro del listado: aplica en vivo (sin guardar) -->
      <template #filters>
        <BaseSelect
          v-model="filters.equipment_type_id"
          :label="t('equipmentSubtypes.fields.type')"
          :options="typeOptions"
        />
      </template>

      <template #meta>
        <p v-if="selected" class="manager-detail__meta">
          {{ t('equipmentSubtypes.fields.type') }}:
          {{ selected.equipment_type ? tr(selected.equipment_type.name) : '—' }}
        </p>
      </template>
    </EntityPanel>
  </div>
</template>
