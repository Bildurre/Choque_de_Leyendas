<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  BaseTabs,
  BlockQuote,
  BlockShell,
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

// Single de facción (portado de public/factions/show.blade.php del viejo),
// ya en el LENGUAJE DE BLOQUES del CRM (blockHeader del registry): lo monta
// EntityDetailView, que pinta el header-bloque (título = nombre, tinte del
// color de la facción, volver dentro) y el fondo/head SEO; aquí cada
// sección es un `block` a lo ancho (block--w-wide + block__inner, como en
// PageView): emblema en su marco de color + lore completo, pestañas héroes
// / cartas / mazos con contadores de publicados (en cliente: la API entrega
// las tres listas completas), cita épica (bloque quote del CRM, tinte gris)
// y relateds de facciones (tarjetas CSS, sin catálogo de previews, bloque
// related del CRM). El botón de descarga de PDF del viejo no se porta: no
// hay endpoint público equivalente (desviación).
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
    <!-- Emblema con el marco del color de la facción + lore: mismo
         contenido de siempre, al ancho del sistema de bloques del CRM -->
    <BlockShell :settings="{ width: 'wide', align: 'left' }">
      <div class="faction-single__header">
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
      </div>
    </BlockShell>

    <!-- Pestañas héroes / cartas / mazos con contadores (BaseTabs del
         motor), también al ancho de bloque -->
    <BlockShell :settings="{ width: 'wide', align: 'left' }">
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
    </BlockShell>

    <!-- Cita épica: EXACTA al bloque quote del CRM (wide, centrada, tinte
         gris por defecto del CRM al 15%) -->
    <BlockQuote
      v-if="quoteHtml"
      :settings="{ quote: quoteHtml, align: 'center', width: 'wide', background: '#64748B' }"
    />

    <!-- Relateds de facciones (tarjetas CSS), excluyendo la actual: bloque
         related del CRM, con título (h2) y botón al índice -->
    <CssCardsRelated
      kind="faction"
      :exclude-id="item.id"
      :title="t('singles.faction.relatedTitle')"
      :button-label="t('singles.faction.relatedButton')"
    />
  </div>
</template>
