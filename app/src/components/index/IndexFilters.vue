<script setup lang="ts">
import { computed, ref, useSlots } from 'vue'
import { useI18n } from 'vue-i18n'
import { Search, SlidersHorizontal } from '@lucide/vue'
import SortToggles from '@/components/index/SortToggles.vue'
import type { SortOption } from '@/entities/catalogSort'

// Sistema de filtros común de los índices públicos: barra con buscador
// (opcional), toggles de orden y botón de despliegue (solo móvil), más un
// panel discreto con los campos (slot) en rejilla por container queries.
// `count` (filtros activos) alimenta el badge del botón y el enlace de
// limpiar; el panel solo existe si la vista mete campos en el slot.
const props = withDefaults(
  defineProps<{
    /** Texto de búsqueda (v-model:search); sin él no hay buscador. */
    search?: string
    /** Orden activo (v-model:sort); sin él no hay toggles. */
    sort?: SortOption
    /** Nº de filtros activos (badge + botón de limpiar). */
    count?: number
    /** id del panel (aria-controls del botón de despliegue). */
    panelId?: string
  }>(),
  { count: 0, panelId: 'index-filters-panel' },
)

const emit = defineEmits<{
  'update:search': [value: string]
  'update:sort': [value: SortOption]
  clear: []
}>()

const { t } = useI18n()
const slots = useSlots()
const open = ref(false)

const hasPanel = computed(() => !!slots.default)

const searchModel = computed({
  get: () => props.search ?? '',
  set: (value: string) => emit('update:search', value),
})

const sortModel = computed({
  get: () => props.sort ?? 'latest',
  set: (value: SortOption) => emit('update:sort', value),
})
</script>

<template>
  <section class="index-filters">
    <div class="index-filters__bar">
      <label v-if="search !== undefined" class="index-filters__search">
        <Search :size="16" class="index-filters__search-icon" aria-hidden="true" />
        <input
          v-model="searchModel"
          type="search"
          class="index-filters__search-input"
          :placeholder="t('catalog.searchPlaceholder')"
          :aria-label="t('catalog.search')"
        />
      </label>

      <SortToggles v-if="sort !== undefined" v-model="sortModel" />

      <!-- En móvil los campos se colapsan tras este botón -->
      <button
        v-if="hasPanel"
        type="button"
        class="index-filters__toggle"
        :aria-expanded="open"
        :aria-controls="panelId"
        :title="t('catalog.filters.toggle')"
        @click="open = !open"
      >
        <SlidersHorizontal :size="16" aria-hidden="true" />
        <span class="index-filters__toggle-text">{{ t('catalog.filters.toggle') }}</span>
        <span v-if="count" class="index-filters__toggle-count">{{ count }}</span>
      </button>
    </div>

    <!-- Panel de campos (siempre visible en escritorio) -->
    <form
      v-if="hasPanel"
      :id="panelId"
      class="index-filters__panel"
      :class="{ 'index-filters__panel--open': open }"
      @submit.prevent
    >
      <div class="index-filters__grid">
        <slot />
      </div>

      <div v-if="count" class="index-filters__footer">
        <button type="button" class="index-filters__clear" @click="emit('clear')">
          {{ t('catalog.filters.clear') }}
        </button>
      </div>
    </form>
  </section>
</template>
