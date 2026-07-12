<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ArrowDownAZ, ArrowDownZA, CalendarArrowDown, CalendarArrowUp } from '@lucide/vue'
import type { SortOption } from '@/entities/catalogSort'

// Toggles de orden de los índices públicos (sustituyen al select): un botón
// de fecha (latest ⇄ oldest) y otro alfabético (name ⇄ name_desc). El activo
// va resaltado y alterna su sentido al repulsarlo; pulsar el inactivo lo
// activa en su primer estado (latest / name).
const model = defineModel<SortOption>({ required: true })

const { t } = useI18n()

const dateActive = computed(() => model.value === 'latest' || model.value === 'oldest')

function toggleDate() {
  model.value = model.value === 'latest' ? 'oldest' : 'latest'
}

function toggleAlpha() {
  model.value = model.value === 'name' ? 'name_desc' : 'name'
}

// Etiqueta del estado que representa el botón (el primero si está inactivo).
const dateLabel = computed(() =>
  t(model.value === 'oldest' ? 'catalog.sort.oldest' : 'catalog.sort.latest'),
)
const alphaLabel = computed(() =>
  t(model.value === 'name_desc' ? 'catalog.sort.nameDesc' : 'catalog.sort.nameAsc'),
)
</script>

<template>
  <div class="sort-toggles" role="group" :aria-label="t('catalog.sort.label')">
    <button
      type="button"
      class="sort-toggles__button"
      :class="{ 'is-active': dateActive }"
      :aria-pressed="dateActive"
      :title="dateLabel"
      @click="toggleDate"
    >
      <component
        :is="model === 'oldest' ? CalendarArrowUp : CalendarArrowDown"
        :size="16"
        aria-hidden="true"
      />
      <span class="sort-toggles__text">{{ dateLabel }}</span>
    </button>

    <button
      type="button"
      class="sort-toggles__button"
      :class="{ 'is-active': !dateActive }"
      :aria-pressed="!dateActive"
      :title="alphaLabel"
      @click="toggleAlpha"
    >
      <component
        :is="model === 'name_desc' ? ArrowDownZA : ArrowDownAZ"
        :size="16"
        aria-hidden="true"
      />
      <span class="sort-toggles__text">{{ alphaLabel }}</span>
    </button>
  </div>
</template>
