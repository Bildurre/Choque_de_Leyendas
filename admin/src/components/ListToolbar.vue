<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { IndexToolbar, type SortValue } from '@edc-motor/ui'

// IndexToolbar del motor con los textos del admin ya puestos: búsqueda,
// toggles de ordenación y botón "Filtros" con badge (solo si la vista tiene
// filtros más allá de la búsqueda). Sustituye al FilterBar + SortSelect; el
// debounce de búsqueda sigue viviendo en useEntityList.
withDefaults(
  defineProps<{
    /** Nº de filtros activos del modal (badge del botón "Filtros"). */
    activeCount?: number
    /** Muestra el botón "Filtros" (la vista abre su FiltersModal). */
    showFilters?: boolean
    /** Oculta los toggles en listados sin contrato de ordenación. */
    showSort?: boolean
  }>(),
  { activeCount: 0, showFilters: false, showSort: true },
)

const search = defineModel<string>({ default: '' })
const sort = defineModel<SortValue>('sort', { default: 'latest' })

defineEmits<{ 'open-filters': [] }>()

const { t } = useI18n()
</script>

<template>
  <IndexToolbar
    v-model="search"
    v-model:sort="sort"
    :search-placeholder="t('common.search')"
    :filters-label="t('common.filters')"
    :active-count="activeCount"
    :show-filters="showFilters"
    :show-sort="showSort"
    :latest-label="t('common.sort.latest')"
    :oldest-label="t('common.sort.oldest')"
    :name-label="t('common.sort.nameAsc')"
    :name-desc-label="t('common.sort.nameDesc')"
    @open-filters="$emit('open-filters')"
  />
</template>
