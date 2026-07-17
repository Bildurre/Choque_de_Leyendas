<script setup lang="ts">
import { computed } from 'vue'
import type { FactionRenderData } from '../types'

// Emblema de facción (proporción 5/7 en /_render, como las cartas): port
// fiel del preview del viejo (previews/faction.blade.php +
// _faction-preview.scss): fondo y marco del color de la facción, emblema a
// sangre y nombre centrado con text-stroke del color. Añade la cita épica y
// la banda de totales (héroes/cartas publicados). El payload es
// Faction::renderData() de la api, YA localizado; aun así se programa
// defensivo: todo puede venir null (el lore no se pinta: no cabe legible).
const props = defineProps<{ item: FactionRenderData; locale: string }>()

// Textos fijos de la propia tarjeta (el resto del payload llega localizado).
const STRINGS: Record<string, Record<string, string>> = {
  heroes: { es: 'héroes', eu: 'heroiak', en: 'heroes' },
  cards: { es: 'cartas', eu: 'kartak', en: 'cards' },
  decks: { es: 'mazos', eu: 'sortak', en: 'decks' },
}

function t(key: string): string {
  return STRINGS[key]?.[props.locale] ?? STRINGS[key]?.es ?? ''
}

// Colores de la facción (vars CSS con fallback neutro, patrón CardRender).
const vars = computed(() => ({
  '--fc': props.item?.color || '#6b7280',
  '--ftx': props.item?.text_is_dark ? '#000000' : '#ffffff',
}))

// Totales presentes (contenido publicado), en orden fijo; el server manda
// heroes_count/cards_count y aquí se pintan solo los que lleguen.
const totals = computed(() => {
  return (
    [
      { key: 'heroes', value: props.item?.heroes_count },
      { key: 'cards', value: props.item?.cards_count },
      { key: 'decks', value: props.item?.decks_count },
    ] as const
  ).filter(
    (entry): entry is { key: 'heroes' | 'cards' | 'decks'; value: number } =>
      typeof entry.value === 'number',
  )
})
</script>

<!-- eslint-disable vue/no-v-html -- HTML del WYSIWYG propio (sanitizado en servidor) -->
<template>
  <div class="game-faction-frame">
    <article class="game-faction" :style="vars">
      <div v-if="item?.icon" class="game-faction__icon">
        <img :src="item.icon" alt="" />
      </div>

      <div class="game-faction__content">
        <h2 v-if="item?.name" class="game-faction__name">{{ item.name }}</h2>
        <div v-if="item?.epic_quote" class="game-faction__quote" v-html="item.epic_quote" />
      </div>

      <footer v-if="totals.length" class="game-faction__totals">
        <span v-for="entry in totals" :key="entry.key" class="game-faction__total">
          <strong>{{ entry.value }}</strong> {{ t(entry.key) }}
        </span>
      </footer>
    </article>
  </div>
</template>
