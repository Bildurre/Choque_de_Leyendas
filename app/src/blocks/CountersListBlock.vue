<script setup lang="ts">
import { computed } from 'vue'
import { BlockShell } from '@edc-motor/ui'

// Bloque con-datos del juego (counters-list del viejo): todos los contadores
// publicados del tipo elegido, con nombre, icono y efecto. Los datos llegan
// resueltos de CountersListBlock::resolveData.
interface CounterItem {
  id: number
  name: string
  effect: string | null
  image: string | null
}

const props = defineProps<{
  settings: Record<string, unknown>
  data: { counter_type?: string; counters?: CounterItem[] }
}>()

const counters = computed(() => props.data.counters ?? [])
</script>

<!-- eslint-disable vue/no-v-html -- HTML del wysiwyg, saneado en servidor (DC-09) -->
<template>
  <BlockShell :settings="settings" class="block--counters-list">
    <h2 v-if="settings.title" class="block__title">{{ settings.title }}</h2>
    <p v-if="settings.subtitle" class="block__subtitle">{{ settings.subtitle }}</p>
    <div v-if="settings.intro" class="block__text rich-content" v-html="settings.intro" />

    <ul v-if="counters.length" class="counter-list" :data-type="data.counter_type">
      <li v-for="counter in counters" :key="counter.id" class="counter-list__item">
        <figure v-if="counter.image" class="counter-list__image-wrapper">
          <img class="counter-list__image" :src="counter.image" alt="" loading="lazy" />
        </figure>
        <div class="counter-list__body">
          <h3 class="counter-list__name">{{ counter.name }}</h3>
          <div v-if="counter.effect" class="counter-list__effect rich-content" v-html="counter.effect" />
        </div>
      </li>
    </ul>
  </BlockShell>
</template>
