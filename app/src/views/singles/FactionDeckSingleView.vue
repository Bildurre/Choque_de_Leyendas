<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { BlockQuote, PreviewGrid, type CatalogItem, type PreviewGridItem } from '@edc-motor/ui'
import AddToCollection from '@/components/AddToCollection.vue'
import FactionDeckCard, { type FactionDeckCardData } from '@/components/FactionDeckCard.vue'
import CssCardsRelated from '@/components/singles/CssCardsRelated.vue'
import DiceIcon from '@/components/singles/DiceIcon.vue'
import InfoBlock from '@/components/singles/InfoBlock.vue'
import { applyOgMeta } from '@/entities/singleOg'
import { sectionDetailRoute, sectionIndexRoute } from '@/entities/singleRoutes'

// Single de mazo (portado de public/faction-decks/show.blade.php del viejo):
// cabecera con la tarjeta CSS del mazo + descripción, pestañas
// info | héroes | cartas — info con la rejilla de estadísticas del viejo
// (básica, coste de las cartas, distribución de dados con sus iconos, tipos
// de carta, clases y superclases de héroe), cartas con su badge "xN" de
// copias —, cita épica y relateds de mazos (tarjetas CSS). Lo monta
// EntityDetailView (banner, fondo, head SEO). El botón de descarga de PDF
// del viejo no se porta: no hay endpoint público equivalente (desviación).
interface FactionRef {
  id: number
  name: string
  slug: string | null
  color: string
  text_is_dark: boolean
}

interface DeckCard extends CatalogItem {
  copies: number
}

interface DeckStats {
  total_cards: number
  unique_cards: number
  average_dice: number
  cards_by_dice: Array<{ dice: number; copies: number }>
  symbols: { R: number; G: number; B: number }
  cards_by_type: Array<{ name: string; copies: number }>
  total_heroes: number
  unique_heroes: number
  heroes_by_class: Array<{ name: string; count: number }>
  heroes_by_superclass: Array<{ name: string; count: number }>
}

interface DeckPayload {
  id: number
  name: Record<string, string>
  slug: Record<string, string>
  description: Record<string, string>
  icon: string | null
  image: string | null
  epic_quote: string
  game_mode: { id: number; name: string } | null
  factions: FactionRef[]
  heroes: CatalogItem[]
  cards: DeckCard[]
  total_heroes: number
  total_cards: number
  stats: DeckStats
}

const props = defineProps<{ item: DeckPayload; locale: string }>()

const { t } = useI18n()

type Tab = 'info' | 'heroes' | 'cards'
const tab = ref<Tab>('info')

const name = computed(
  () => props.item.name[props.locale] || Object.values(props.item.name)[0] || '',
)

const stats = computed(() => props.item.stats)

// La tarjeta CSS del mazo como emblema de cabecera (el slug de la tarjeta
// exige string: la facción sin publicar llega con slug null).
const deckCardData = computed<FactionDeckCardData>(() => ({
  name: name.value,
  icon: props.item.icon,
  game_mode: props.item.game_mode,
  factions: props.item.factions.map((faction) => ({ ...faction, slug: faction.slug ?? '' })),
  total_heroes: props.item.total_heroes,
  total_cards: props.item.total_cards,
}))

const description = computed(
  () =>
    props.item.description?.[props.locale] || Object.values(props.item.description ?? {})[0] || '',
)

// Los dados con presencia en el mazo, en orden R → G → B.
const SYMBOLS: Array<{ key: 'R' | 'G' | 'B'; type: 'red' | 'green' | 'blue' }> = [
  { key: 'R', type: 'red' },
  { key: 'G', type: 'green' },
  { key: 'B', type: 'blue' },
]
const symbols = computed(() => SYMBOLS.filter(({ key }) => props.item.stats.symbols[key] > 0))

const tabs = computed<Array<{ id: Tab; label: string; count: number | null }>>(() => [
  { id: 'info', label: t('singles.deck.tabs.info'), count: null },
  { id: 'heroes', label: t('singles.deck.tabs.heroes'), count: props.item.stats.total_heroes },
  { id: 'cards', label: t('singles.deck.tabs.cards'), count: props.item.stats.total_cards },
])

const decksIndexRoute = computed(() => sectionIndexRoute('decks', props.locale))

function factionRoute(faction: FactionRef) {
  return sectionDetailRoute('factions', faction.slug, props.locale)
}

const heroItems = computed<PreviewGridItem[]>(() =>
  props.item.heroes.map((row) => ({
    ...row,
    to: sectionDetailRoute('heroes', row.slug, props.locale),
  })),
)

const cardItems = computed<Array<PreviewGridItem & { copies: number }>>(() =>
  props.item.cards.map((row) => ({
    ...row,
    to: sectionDetailRoute('cards', row.slug, props.locale),
  })),
)

const quoteHtml = computed(() => {
  const quote = props.item.epic_quote?.trim() ?? ''
  if (!quote) return ''
  return quote.startsWith('<') ? quote : `<p>${quote}</p>`
})

// og:* tras el head de EntityDetailView; al cambiar de mazo se vuelve a la
// pestaña de información (patrón del viejo).
watch(
  () => props.item,
  async () => {
    tab.value = 'info'
    await nextTick()
    applyOgMeta({ image: props.item.image, type: 'article' })
  },
  { immediate: true },
)
</script>

<!-- eslint-disable vue/no-v-html -- HTML del wysiwyg propio, saneado en servidor -->
<template>
  <div class="deck-single">
    <!-- Cabecera: tarjeta CSS del mazo + descripción -->
    <section class="deck-single__header">
      <div class="deck-single__emblem">
        <FactionDeckCard :deck="deckCardData" />
      </div>
      <div v-if="description" class="deck-single__lore rich-content" v-html="description" />
    </section>

    <!-- Pestañas info | héroes | cartas -->
    <div class="single-tabs" role="tablist">
      <button
        v-for="entry in tabs"
        :key="entry.id"
        type="button"
        role="tab"
        class="single-tabs__tab"
        :class="{ 'is-active': tab === entry.id }"
        :aria-selected="tab === entry.id"
        @click="tab = entry.id"
      >
        {{ entry.label }}
        <span v-if="entry.count !== null" class="single-tabs__count">{{ entry.count }}</span>
      </button>
    </div>

    <!-- Información: la rejilla de estadísticas del viejo -->
    <div v-if="tab === 'info'" class="info-blocks-grid">
      <InfoBlock :title="t('singles.deck.basicInfo')">
        <dl class="info-list">
          <dt>{{ t('singles.deck.name') }}</dt>
          <dd>{{ name }}</dd>

          <template v-if="item.factions.length">
            <dt>{{ t('singles.deck.factions') }}</dt>
            <dd>
              <template v-for="(faction, i) in item.factions" :key="faction.id">
                <RouterLink
                  v-if="factionRoute(faction)"
                  class="info-link"
                  :to="factionRoute(faction)!"
                >
                  {{ faction.name }}
                </RouterLink>
                <template v-else>{{ faction.name }}</template>
                <template v-if="i < item.factions.length - 1">, </template>
              </template>
            </dd>
          </template>

          <template v-if="item.game_mode">
            <dt>{{ t('singles.deck.gameMode') }}</dt>
            <dd>
              <RouterLink v-if="decksIndexRoute" class="info-link" :to="decksIndexRoute">
                {{ item.game_mode.name }}
              </RouterLink>
              <template v-else>{{ item.game_mode.name }}</template>
            </dd>
          </template>

          <dt>{{ t('singles.deck.heroes') }}</dt>
          <dd>
            {{
              t('singles.deck.uniqueHeroes', {
                total: stats.total_heroes,
                unique: stats.unique_heroes,
              })
            }}
          </dd>

          <dt>{{ t('singles.deck.cards') }}</dt>
          <dd>
            {{
              t('singles.deck.uniqueCards', {
                total: stats.total_cards,
                unique: stats.unique_cards,
              })
            }}
          </dd>
        </dl>
      </InfoBlock>

      <InfoBlock v-if="stats.cards_by_dice.length" :title="t('singles.deck.diceDistribution')">
        <dl class="info-list">
          <template v-for="row in stats.cards_by_dice" :key="row.dice">
            <dt>
              {{
                row.dice === 0
                  ? t('singles.deck.noDice')
                  : t('singles.deck.dice', { count: row.dice }, row.dice)
              }}
            </dt>
            <dd>{{ row.copies }}</dd>
          </template>

          <dt>{{ t('singles.deck.average') }}</dt>
          <dd>{{ stats.average_dice.toFixed(2) }}</dd>
        </dl>
      </InfoBlock>

      <InfoBlock v-if="symbols.length" :title="t('singles.deck.symbolDistribution')">
        <dl class="info-list">
          <template v-for="symbol in symbols" :key="symbol.key">
            <dt><DiceIcon :type="symbol.type" size="sm" /></dt>
            <dd>{{ stats.symbols[symbol.key] }}</dd>
          </template>
        </dl>
      </InfoBlock>

      <InfoBlock v-if="stats.cards_by_type.length" :title="t('singles.deck.cardTypes')">
        <dl class="info-list">
          <template v-for="row in stats.cards_by_type" :key="row.name">
            <dt>{{ row.name }}</dt>
            <dd>{{ row.copies }}</dd>
          </template>
        </dl>
      </InfoBlock>

      <InfoBlock
        v-if="stats.heroes_by_superclass.length"
        :title="t('singles.deck.heroSuperclasses')"
      >
        <dl class="info-list">
          <template v-for="row in stats.heroes_by_superclass" :key="row.name">
            <dt>{{ row.name }}</dt>
            <dd>{{ row.count }}</dd>
          </template>
        </dl>
      </InfoBlock>

      <InfoBlock v-if="stats.heroes_by_class.length" :title="t('singles.deck.heroClasses')">
        <dl class="info-list">
          <template v-for="row in stats.heroes_by_class" :key="row.name">
            <dt>{{ row.name }}</dt>
            <dd>{{ row.count }}</dd>
          </template>
        </dl>
      </InfoBlock>
    </div>

    <!-- Héroes del mazo -->
    <PreviewGrid
      v-else-if="tab === 'heroes'"
      :items="heroItems"
      :empty-text="t('singles.deck.noHeroes')"
      class="single-grid"
    >
      <template #actions="{ item: hero }">
        <AddToCollection :id="hero.id" class="single-grid__add" entity="hero" />
      </template>
    </PreviewGrid>

    <!-- Cartas del mazo, con el badge de copias del viejo -->
    <PreviewGrid
      v-else
      :items="cardItems"
      :empty-text="t('singles.deck.noCards')"
      class="single-grid"
    >
      <template #item="{ item: card }">
        <img
          v-if="card.preview"
          class="preview-grid__image"
          :src="card.preview"
          :alt="card.name"
          loading="lazy"
        />
        <span v-else class="preview-grid__fallback">{{ card.name }}</span>
        <span class="deck-single__copies">
          {{ t('singles.deck.copies', { count: (card as DeckCard).copies }) }}
        </span>
      </template>
      <template #actions="{ item: card }">
        <AddToCollection :id="card.id" class="single-grid__add" entity="card" />
      </template>
    </PreviewGrid>

    <!-- Cita épica (bloque quote del motor, centrado como el viejo) -->
    <BlockQuote v-if="quoteHtml" :settings="{ quote: quoteHtml, align: 'center' }" />

    <!-- Relateds de mazos (tarjetas CSS), excluyendo el actual -->
    <CssCardsRelated
      kind="deck"
      :exclude-id="item.id"
      :subtitle="t('singles.deck.relatedSubtitle')"
      :button-label="t('singles.deck.relatedButton')"
    />
  </div>
</template>
