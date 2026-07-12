<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { FiltersModal } from '@edc-motor/ui'

// FiltersModal del motor con los textos del admin ya puestos. Los campos del
// slot aplican EN VIVO (v-model directo sobre `filters` de useEntityList);
// "Quitar filtros" (emit `clear`) limpia solo los filtros del modal, nunca
// la búsqueda ni el orden.
withDefaults(
  defineProps<{
    /** Nº de filtros activos (muestra "Quitar filtros" y el badge). */
    activeCount?: number
    size?: 'sm' | 'md' | 'lg'
  }>(),
  { activeCount: 0, size: 'md' },
)

const open = defineModel<boolean>({ default: false })

defineEmits<{ clear: [] }>()

const { t } = useI18n()
</script>

<template>
  <FiltersModal
    v-model="open"
    :title="t('common.filters')"
    :size="size"
    :active-count="activeCount"
    :clear-label="t('common.clearFilters')"
    :close-label="t('common.close')"
    @clear="$emit('clear')"
  >
    <slot />
  </FiltersModal>
</template>
