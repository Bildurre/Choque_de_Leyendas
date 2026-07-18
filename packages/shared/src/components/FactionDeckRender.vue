<script setup lang="ts">
import { computed } from 'vue'
import type { DeckRenderFaction, FactionDeckRenderData } from '../types'

// Ficha de mazo (proporción 5/7 en /_render, como las cartas): port del
// preview del viejo (previews/deck.blade.php + _deck-preview.scss): cabecera
// con el color de la facción (o el GRADIENTE por bandas si es multifacción),
// icono del mazo, modo de juego y chips de facciones; añade los totales y el
// resumen de héroes y cartas (con copias). El payload es
// FactionDeck::renderData() de la api, YA localizado; aun así se programa
// defensivo: todo puede venir null.
const props = defineProps<{ item: FactionDeckRenderData; locale: string }>()

// Textos fijos de la propia ficha (el resto del payload llega localizado).
const STRINGS: Record<string, Record<string, string>> = {
  heroes: { es: 'Héroes', eu: 'Heroiak', en: 'Heroes' },
  cards: { es: 'Cartas', eu: 'Kartak', en: 'Cards' },
  gameTitle: { es: 'Espadas de Ceniza', eu: 'Espadas de Ceniza', en: 'Blades of Ash' },
  gameSubtitle: { es: 'Choque de Leyendas', eu: 'Choque de Leyendas', en: 'Clash of Legends' },
}

function t(key: string): string {
  return STRINGS[key]?.[props.locale] ?? STRINGS[key]?.es ?? ''
}

// Texto legible sobre una facción: text_is_dark del server si llega, y si
// no, luminancia YIQ del color (mismo criterio que el backend).
function textOn(faction?: Pick<DeckRenderFaction, 'color' | 'text_is_dark'> | null): string {
  if (typeof faction?.text_is_dark === 'boolean') {
    return faction.text_is_dark ? '#000000' : '#ffffff'
  }
  const hex = (faction?.color ?? '').replace('#', '')
  if (!/^[0-9a-f]{6}$/i.test(hex)) return '#ffffff'
  const [r, g, b] = [0, 2, 4].map((i) => parseInt(hex.slice(i, i + 2), 16))
  return (r * 299 + g * 587 + b * 114) / 1000 >= 128 ? '#000000' : '#ffffff'
}

const factions = computed<DeckRenderFaction[]>(() =>
  (props.item?.factions ?? []).filter((f) => !!(f?.name || f?.color)),
)

const isMulti = computed(() => factions.value.length > 1)

// Gradiente por bandas iguales (mismo cálculo que getGradientCss() del viejo).
const gradient = computed(() => {
  const colors = factions.value.map((f) => f.color || '#6b7280')
  if (colors.length <= 1) return colors[0] ?? '#6b7280'
  const band = 100 / colors.length
  const stops = colors.map((c, i) => `${c} ${i * band}%, ${c} ${(i + 1) * band}%`)
  return `linear-gradient(135deg, ${stops.join(', ')})`
})

const vars = computed(() => {
  const first = factions.value[0]
  return {
    '--fc': first?.color || '#6b7280',
    '--ftx': textOn(first),
    '--dg': gradient.value,
  }
})

// Modo de juego: llega como string YA localizado.
const mode = computed(() => props.item?.game_mode ?? '')

// Totales de contenido publicado (las cartas suman copias), solo los que
// lleguen numéricos.
const totals = computed(() => {
  return (
    [
      { key: 'heroes', value: props.item?.total_heroes },
      { key: 'cards', value: props.item?.total_cards },
    ] as const
  ).filter(
    (entry): entry is { key: 'heroes' | 'cards'; value: number } => typeof entry.value === 'number',
  )
})

// Héroes: nombre localizado + copias (se tolera también un array de strings).
const heroes = computed(() =>
  (props.item?.heroes ?? [])
    .map((h) =>
      typeof h === 'string'
        ? { name: h, copies: null as number | null }
        : { name: h?.name ?? '', copies: h?.copies ?? null },
    )
    .filter((h) => !!h.name),
)

const cards = computed(() => (props.item?.cards ?? []).filter((c) => !!c?.name))
</script>

<template>
  <div class="game-deck-frame">
    <article class="game-deck" :class="{ 'game-deck--multi': isMulti }" :style="vars">
      <!-- Cabecera con el color (o gradiente) de las facciones del mazo -->
      <header class="game-deck__header">
        <div class="game-deck__titles">
          <h2 v-if="item?.name" class="game-deck__name">{{ item.name }}</h2>
          <h3 v-if="mode" class="game-deck__mode">{{ mode }}</h3>
        </div>
        <div v-if="item?.icon" class="game-deck__icon">
          <img :src="item.icon" alt="" />
        </div>
      </header>

      <!-- Chips de facciones, cada una con su color -->
      <div v-if="factions.length" class="game-deck__factions">
        <span
          v-for="(faction, i) in factions"
          :key="i"
          class="game-deck__faction"
          :style="{ background: faction.color || '#6b7280', color: textOn(faction) }"
        >
          {{ faction.name }}
        </span>
      </div>

      <!-- Totales del mazo -->
      <div v-if="totals.length" class="game-deck__totals">
        <span v-for="entry in totals" :key="entry.key" class="game-deck__total">
          <strong>{{ entry.value }}</strong> {{ t(entry.key).toLowerCase() }}
        </span>
      </div>

      <!-- Resumen: héroes y cartas (con copias) -->
      <section class="game-deck__lists">
        <div v-if="heroes.length" class="game-deck__list">
          <h4 class="game-deck__list-title">{{ t('heroes') }}</h4>
          <ul>
            <li v-for="(hero, i) in heroes" :key="i">
              {{ hero.name }}
              <b v-if="hero.copies && hero.copies > 1" class="game-deck__copies"
                >x{{ hero.copies }}</b
              >
            </li>
          </ul>
        </div>
        <div v-if="cards.length" class="game-deck__list game-deck__list--cards">
          <h4 class="game-deck__list-title">{{ t('cards') }}</h4>
          <ul>
            <li v-for="(card, i) in cards" :key="i">
              {{ card.name }}
              <b v-if="card.copies && card.copies > 1" class="game-deck__copies"
                >x{{ card.copies }}</b
              >
            </li>
          </ul>
        </div>
      </section>

      <footer class="game-deck__footer">
        <span class="game-deck__footer-title">{{ t('gameTitle') }}:</span>
        <span class="game-deck__footer-subtitle">{{ t('gameSubtitle') }}</span>
      </footer>
    </article>
  </div>
</template>
