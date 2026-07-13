<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { IndexToolbar, type SortValue } from '@edc-motor/ui'

// IndexToolbar del motor con los textos del admin ya puestos: búsqueda y
// toggles de ordenación. Sustituye al FilterBar + SortSelect; el debounce de
// búsqueda sigue viviendo en useEntityList. Los filtros del listado viven en
// el panel derecho (useRightSidebar), no aquí.
withDefaults(
  defineProps<{
    /** Oculta los toggles en listados sin contrato de ordenación. */
    showSort?: boolean
  }>(),
  { showSort: true },
)

const search = defineModel<string>({ default: '' })
const sort = defineModel<SortValue>('sort', { default: 'latest' })

const { t } = useI18n()
</script>

<template>
  <IndexToolbar
    v-model="search"
    v-model:sort="sort"
    :search-placeholder="t('common.search')"
    :show-sort="showSort"
    :latest-label="t('common.sort.latest')"
    :oldest-label="t('common.sort.oldest')"
    :name-label="t('common.sort.nameAsc')"
    :name-desc-label="t('common.sort.nameDesc')"
  />
</template>
