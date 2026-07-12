<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  BaseTabs,
  BlockQuote,
  PreviewGrid,
  type CatalogItem,
  type PreviewGridItem,
} from '@edc-motor/ui'
import AddToCollection from '@/components/AddToCollection.vue'
import FactionCard from '@/components/FactionCard.vue'
import FactionDeckCard, { type FactionDeckCardData } from '@/components/FactionDeckCard.vue'
import CssCardsRelated from '@/components/singles/CssCardsRelated.vue'
import { applyOgMeta } from '@/entities/singleOg'
import { sectionDetailRoute } from '@/entities/singleRoutes'

// Single de facción (portado de public/factions/show.blade.php del viejo):
// cabecera con el emblema en su marco de color + lore completo, pestañas
// héroes / cartas / mazos con contadores de publicados (aquí en cliente:
// la API entrega las tres listas completas), cita épica y relateds de
// facciones (tarjetas CSS, sin catálogo de previews). Lo monta
// EntityDetailView (banner, fondo, head SEO). El botón de descarga de PDF
// del viejo no se porta: no hay endpoint público equivalente (desviación).
interface DeckRow extends FactionDeckCardData {
  id: number
  slug: string
}

interface FactionPayload {
  id: number
  name: Record<string, string>
  slug: Record<string, string>
  color: string
  text_is_dark: boolean
  icon: string | null
  image: string | null
  lore_text: string
  epic_quote: string
  heroes_count: number
  cards_count: number
  decks_count: number
  heroes: CatalogItem[]
  cards: CatalogItem[]
  decks: DeckRow[]
}

const props = defineProps<{ item: FactionPayload; locale: string }>()

const { t } = useI18n()

type Tab = 'heroes' | 'cards' | 'decks'
const tab = ref<Tab>('heroes')

const name = computed(
  () => props.item.name[props.locale] || Object.values(props.item.name)[0] || '',
)

const style = computed(() => ({
  '--faction-color': props.item.color,
  '--faction-text': props.item.text_is_dark ? '#000000' : '#ffffff',
}))

// Pestañas del BaseTabs del motor, con los contadores de publicados.
const tabs = computed<Array<{ key: Tab; label: string; count: number }>>(() => [
  { key: 'heroes', label: t('singles.faction.tabs.heroes'), count: props.item.heroes_count },
  { key: 'cards', label: t('singles.faction.tabs.cards'), count: props.item.cards_count },
  { key: 'decks', label: t('singles.faction.tabs.decks'), count: props.item.decks_count },
])

function setTab(key: string) {
  tab.value = key as Tab
}

function withRoute(items: CatalogItem[], sectionKey: string): PreviewGridItem[] {
  return items.map((row) => ({
    ...row,
    to: sectionDetailRoute(sectionKey, row.slug, props.locale),
  }))
}

const heroItems = computed(() => withRoute(props.item.heroes, 'heroes'))
const cardItems = computed(() => withRoute(props.item.cards, 'cards'))

function deckRoute(deck: DeckRow) {
  return sectionDetailRoute('decks', deck.slug, props.locale)
}

const quoteHtml = computed(() => {
  const quote = props.item.epic_quote?.trim() ?? ''
  if (!quote) return ''
  return quote.startsWith('<') ? quote : `<p>${quote}</p>`
})

// og:* tras el head de EntityDetailView; al cambiar de facción se vuelve a
// la primera pestaña (patrón del viejo, que arrancaba en héroes).
watch(
  () => props.item,
  async () => {
    tab.value = 'heroes'
    await nextTick()
    applyOgMeta({ image: props.item.image, type: 'profile' })
  },
  { immediate: true },
)
</script>

<!-- eslint-disable vue/no-v-html -- HTML del wysiwyg propio, saneado en servidor -->
<template>
  <div class="faction-single" :style="style">
    <!-- Cabecera: emblema con el marco del color de la facción + lore -->
    <section class="faction-single__header">
      <div class="faction-single__emblem">
        <FactionCard
          :faction="{ name, color: item.color, text_is_dark: item.text_is_dark, icon: item.icon }"
        />
      </div>
      <div
        v-if="item.lore_text"
        class="faction-single__lore rich-content"
        v-html="item.lore_text"
      />
    </section>

    <!-- Pestañas héroes / cartas / mazos con contadores (BaseTabs del motor) -->
    <BaseTabs
      class="faction-single__tabs"
      :tabs="tabs"
      :model-value="tab"
      @update:model-value="setTab"
    />

    <PreviewGrid
      v-if="tab === 'heroes'"
      :items="heroItems"
      :empty-text="t('singles.faction.noHeroes')"
      class="single-grid"
    >
      <template #actions="{ item: hero }">
        <AddToCollection :id="hero.id" class="single-grid__add" entity="hero" />
      </template>
    </PreviewGrid>

    <PreviewGrid
      v-else-if="tab === 'cards'"
      :items="cardItems"
      :empty-text="t('singles.faction.noCards')"
      class="single-grid"
    >
      <template #actions="{ item: card }">
        <AddToCollection :id="card.id" class="single-grid__add" entity="card" />
      </template>
    </PreviewGrid>

    <template v-else>
      <p v-if="!item.decks.length" class="faction-single__empty">
        {{ t('singles.faction.noDecks') }}
      </p>
      <div v-else class="css-related-grid faction-single__decks">
        <component
          :is="deckRoute(deck) ? RouterLink : 'div'"
          v-for="deck in item.decks"
          :key="deck.id"
          class="css-related-grid__item"
          v-bind="deckRoute(deck) ? { to: deckRoute(deck) } : {}"
        >
          <FactionDeckCard :deck="deck" />
        </component>
      </div>
    </template>

    <!-- Cita épica (bloque quote del motor, centrado como el viejo) -->
    <BlockQuote v-if="quoteHtml" :settings="{ quote: quoteHtml, align: 'center' }" />

    <!-- Relateds de facciones (tarjetas CSS), excluyendo la actual -->
    <CssCardsRelated
      kind="faction"
      :exclude-id="item.id"
      :subtitle="t('singles.faction.relatedSubtitle')"
      :button-label="t('singles.faction.relatedButton')"
    />
  </div>
</template>
