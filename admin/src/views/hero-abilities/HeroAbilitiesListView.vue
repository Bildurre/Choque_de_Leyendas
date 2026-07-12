<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BasePagination, BaseSelect, BaseTabs } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { useEntityList } from '@/composables/useEntityList'
import type { HeroAbility, TaxonomyOption } from '@juego/shared'
import HeroAbilityFormModal from '@/components/hero-abilities/HeroAbilityFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'
import ListFiltersModal from '@/components/ListFiltersModal.vue'
import ListToolbar from '@/components/ListToolbar.vue'
import CostDice from '@/components/game/CostDice.vue'

// Habilidades activas: sin single ni publicación (tabs all/trashed); la API
// resuelve por id y la edición rellena desde el ítem del listado. El listado
// filtra por tipo/rango/subtipo de ataque, área y coste total con selects en
// el modal de filtros (mecanismo `filters` de useEntityList).
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
  filtersOpen,
  activeFiltersCount,
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
  del,
  restore,
  forceDelete,
  selectedId,
  selected,
  select,
} = useEntityList<HeroAbility>({
  resource: '/admin/hero-abilities',
  ns: 'heroAbilities',
  nameOf: (item) => item.name,
  resolveBy: 'id',
  tabKeys: ['all', 'trashed'],
})

const attackTypeOptions = computed(() => [
  { value: '', label: t('heroAbilities.filters.allAttackTypes') },
  { value: 'physical', label: t('heroAbilities.attackTypes.physical') },
  { value: 'magical', label: t('heroAbilities.attackTypes.magical') },
])

// Taxonomías de los selects de rango/subtipo (mismos options del modal).
const attackRanges = ref<TaxonomyOption[]>([])
const attackSubtypes = ref<TaxonomyOption[]>([])

async function loadFilterOptions() {
  try {
    const [ranges, subtypes] = await Promise.all([
      api.get('/admin/attack-ranges/options'),
      api.get('/admin/attack-subtypes/options'),
    ])
    attackRanges.value = ranges.data.data
    attackSubtypes.value = subtypes.data.data
  } catch {
    attackRanges.value = []
    attackSubtypes.value = []
  }
}

const attackRangeOptions = computed(() => [
  { value: '', label: t('heroAbilities.filters.allAttackRanges') },
  ...attackRanges.value.map((o) => ({ value: String(o.id), label: tr(o.name) })),
])

const attackSubtypeOptions = computed(() => [
  { value: '', label: t('heroAbilities.filters.allAttackSubtypes') },
  ...attackSubtypes.value.map((o) => ({ value: String(o.id), label: tr(o.name) })),
])

// area viaja como '1'/'0' ('' = no filtra), contrato del endpoint.
const areaOptions = computed(() => [
  { value: '', label: t('heroAbilities.filters.allAreas') },
  { value: '1', label: t('heroAbilities.filters.areaYes') },
  { value: '0', label: t('heroAbilities.filters.areaNo') },
])

// cost_total 1..5: el coste de una habilidad es obligatorio, 0 no existe.
const costOptions = computed(() => [
  { value: '', label: t('heroAbilities.filters.allCosts') },
  ...[1, 2, 3, 4, 5].map((n) => ({
    value: String(n),
    label: t('heroAbilities.filters.dice', { n }, n),
  })),
])

/** Tipado completo en orden canónico: rango · tipo · subtipo · área. */
function typing(a: HeroAbility): string {
  const parts: string[] = []
  if (a.attack_range) parts.push(tr(a.attack_range.name))
  if (a.attack_type) parts.push(t(`heroAbilities.attackTypes.${a.attack_type}`))
  if (a.attack_subtype) parts.push(tr(a.attack_subtype.name))
  if (a.area) parts.push(t('heroAbilities.fields.area'))
  return parts.join(' · ')
}

onMounted(() => {
  init()
  loadFilterOptions()
})
</script>

<!-- eslint-disable vue/no-v-html -- HTML del WYSIWYG propio (sanitización en servidor) -->
<template>
  <div class="hero-abilities">
    <div class="list-view__top">
      <BaseButton @click="openCreate">
        <template #icon><Plus :size="16" /></template>
        {{ t('heroAbilities.newButton') }}
      </BaseButton>
    </div>

    <!-- Barra del índice: búsqueda + orden + botón "Filtros" (modal) -->
    <ListToolbar
      v-model="search"
      v-model:sort="sort"
      show-filters
      :active-count="activeFiltersCount"
      @open-filters="filtersOpen = true"
    />
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
            t('heroAbilities.state.trashed')
          }}</span>
        </template>

        <template #meta>
          <CostDice v-if="item.cost" :cost="item.cost" />
          <span v-if="typing(item)">{{ typing(item) }}</span>
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

    <!-- Filtros del listado: aplican en vivo (sin guardar) -->
    <ListFiltersModal
      v-model="filtersOpen"
      :active-count="activeFiltersCount"
      @clear="clearFilters"
    >
      <!-- Orden canónico del tipado: rango · tipo · subtipo · área -->
      <BaseSelect
        v-model="filters.attack_range_id"
        :label="t('heroAbilities.fields.attackRange')"
        :options="attackRangeOptions"
      />
      <BaseSelect
        v-model="filters.attack_type"
        :label="t('heroAbilities.fields.attackType')"
        :options="attackTypeOptions"
      />
      <BaseSelect
        v-model="filters.attack_subtype_id"
        :label="t('heroAbilities.fields.attackSubtype')"
        :options="attackSubtypeOptions"
      />
      <BaseSelect
        v-model="filters.area"
        :label="t('heroAbilities.fields.area')"
        :options="areaOptions"
      />
      <BaseSelect
        v-model="filters.cost_total"
        :label="t('heroAbilities.fields.cost')"
        :options="costOptions"
      />
    </ListFiltersModal>

    <HeroAbilityFormModal v-model="formOpen" :mode="formMode" :target="formItem" @saved="onSaved" />

    <EntityPanel
      :item="selected"
      :name="selected ? tr(selected.name) : ''"
      :kicker="t('heroAbilities.panelTitle')"
      :empty="t('heroAbilities.panelEmpty')"
      :has-single="false"
      :has-publish="false"
      @edit="selected && edit(selected)"
      @del="selected && del(selected)"
      @restore="selected && restore(selected)"
      @force-delete="selected && forceDelete(selected)"
    >
      <template #meta>
        <!-- Tipado completo en orden canónico: rango · tipo · subtipo · área -->
        <p v-if="selected" class="manager-detail__meta">
          <CostDice v-if="selected.cost" :cost="selected.cost" />
          <span v-if="typing(selected)">{{ typing(selected) }}</span>
        </p>
        <!-- HTML del WYSIWYG propio (saneado en origen) -->
        <div
          v-if="selected && tr(selected.description) !== '—'"
          class="hero-abilities__description"
          v-html="tr(selected.description)"
        ></div>
      </template>
    </EntityPanel>
  </div>
</template>
