<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

// Tarjeta CSS de mazo (portada del viejo `previews/deck.blade.php` +
// `_deck-preview.scss`): como la de facción, con el nombre del modo arriba,
// badge de facción (o "multifacción") abajo y, si es multifacción, marco y
// vars con GRADIENTE por bandas de los colores de sus facciones. Añade los
// totales de héroes/cartas del índice nuevo.
export interface DeckCardFaction {
  id: number
  name: string
  slug: string
  color: string
  text_is_dark: boolean
}

export interface FactionDeckCardData {
  name: string
  icon: string | null
  game_mode: { id: number; name: string } | null
  factions: DeckCardFaction[]
  total_heroes: number
  total_cards: number
}

const props = defineProps<{ deck: FactionDeckCardData }>()

const { t } = useI18n()

const isMulti = computed(() => props.deck.factions.length > 1)

const textFor = (faction?: DeckCardFaction) => (faction?.text_is_dark ? '#000000' : '#ffffff')

// Gradiente por bandas iguales (mismo cálculo que getGradientCss() del viejo).
const gradient = computed(() => {
  const colors = props.deck.factions.map((f) => f.color)
  if (!colors.length) return '#000000'
  if (colors.length === 1) return colors[0]
  const band = 100 / colors.length
  const stops = colors.map((c, i) => `${c} ${i * band}%, ${c} ${(i + 1) * band}%`)
  return `linear-gradient(135deg, ${stops.join(', ')})`
})

const style = computed(() => {
  const first = props.deck.factions[0]
  const last = props.deck.factions[props.deck.factions.length - 1]
  const left = first?.color ?? '#000000'
  const right = last?.color ?? left
  return {
    '--faction-color': isMulti.value ? 'transparent' : left,
    '--faction-gradient': gradient.value,
    '--faction-text': isMulti.value ? '#ffffff' : textFor(first),
    '--mode-bg': left,
    '--mode-text': textFor(first),
    '--badge-bg': isMulti.value ? right : left,
    '--badge-text': isMulti.value ? textFor(last) : textFor(first),
  }
})

const badge = computed(() =>
  isMulti.value ? t('decks.multiFaction') : (props.deck.factions[0]?.name ?? ''),
)
</script>

<template>
  <article class="deck-card" :class="{ 'deck-card--multi': isMulti }" :style="style">
    <p v-if="deck.game_mode" class="deck-card__mode">{{ deck.game_mode.name }}</p>

    <div v-if="deck.icon" class="deck-card__icon">
      <img :src="deck.icon" :alt="deck.name" loading="lazy" />
    </div>

    <div class="deck-card__content">
      <h3 class="deck-card__name">{{ deck.name }}</h3>
      <p class="deck-card__totals">
        {{ t('decks.totals', { heroes: deck.total_heroes, cards: deck.total_cards }) }}
      </p>
    </div>

    <span v-if="badge" class="deck-card__badge">{{ badge }}</span>
  </article>
</template>
