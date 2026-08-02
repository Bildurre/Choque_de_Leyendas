<script setup lang="ts">
import { computed, nextTick, ref, watch, type Component } from 'vue'
import { useI18n } from 'vue-i18n'
import { Files, Users } from '@lucide/vue'
import {
  BaseTabs,
  BlockQuote,
  BlockShell,
  PreviewGrid,
  type CatalogItem,
  type PreviewGridItem,
} from '@edc-motor/ui'
import AddToCollection from '@/components/AddToCollection.vue'
import CssCardsRelated from '@/components/singles/CssCardsRelated.vue'
import DiceIcon from '@/components/singles/DiceIcon.vue'
import InfoBlock from '@/components/singles/InfoBlock.vue'
import { applyOgMeta } from '@/entities/singleOg'
import { sectionDetailRoute, sectionIndexRoute } from '@/entities/singleRoutes'

// Single de mazo (portado de public/faction-decks/show.blade.php del viejo),
// ya en el LENGUAJE DE BLOQUES del CRM (blockHeader del registry): lo monta
// EntityDetailView, que pinta el header-bloque (título = nombre, tinte GRIS
// por defecto — un mazo mezcla facciones — y volver + acciones dentro; sin
// añadir: los mazos no son coleccionables; SÍ descarga de su PDF permanente
// si está generado, campo `pdf` del payload) y el fondo/head SEO. Aquí cada
// sección es un `block` a lo ancho: ficha en GRID de TRES columnas fijas
// (.deck-detail-grid, cortes explícitos de container query a 2 → 1) con
// filas fijas — fila 1 la card de IMAGEN del mazo (1 columna, solo si hay
// imagen; recortada al alto de la descripción) + la DESCRIPCIÓN (tarjeta
// sin título, rich del wysiwyg, 2 columnas; a las tres sin imagen), fila 2
// información básica | coste de las cartas | tipos de cartas, fila 3
// distribución de dados | clases de héroes
// | superclases de héroes —, pestañas héroes | cartas CON icono (cartas con
// su badge "xN" de copias; los héroes no llevan copias: asignado = 1), cita
// épica (bloque quote del CRM, fondo DINÁMICO del tema token:surface-3, OPACO) y
// relateds de mazos (tarjetas CSS, bloque related del CRM). El modo de
// juego enlaza al ÍNDICE de mazos FILTRADO por ese modo (query `mode` de
// useFiltersQuery).
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

type DeckHero = CatalogItem

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
  heroes: DeckHero[]
  cards: DeckCard[]
  total_heroes: number
  total_cards: number
  stats: DeckStats
}

const props = defineProps<{ item: DeckPayload; locale: string }>()

const { t } = useI18n()

type Tab = 'heroes' | 'cards'
const tab = ref<Tab>('heroes')

const name = computed(
  () => props.item.name[props.locale] || Object.values(props.item.name)[0] || '',
)

const stats = computed(() => props.item.stats)

// Los dados con presencia en el mazo, en orden R → G → B.
const SYMBOLS: Array<{ key: 'R' | 'G' | 'B'; type: 'red' | 'green' | 'blue' }> = [
  { key: 'R', type: 'red' },
  { key: 'G', type: 'green' },
  { key: 'B', type: 'blue' },
]
const symbols = computed(() => SYMBOLS.filter(({ key }) => props.item.stats.symbols[key] > 0))

// Pestañas del BaseTabs del motor: héroes | cartas — héroes → Users (tipo
// usuarios), cartas → Files (tipo archivos); en estrecho el motor deja solo
// el icono (texto visually-hidden + title).
const tabs = computed<Array<{ key: Tab; label: string; count?: number; icon?: Component }>>(() => [
  {
    key: 'heroes',
    label: t('singles.deck.tabs.heroes'),
    count: props.item.stats.total_heroes,
    icon: Users,
  },
  {
    key: 'cards',
    label: t('singles.deck.tabs.cards'),
    count: props.item.stats.total_cards,
    icon: Files,
  },
])

// Descripción del wysiwyg TAL CUAL (rich, saneada en servidor): tarjeta sin
// título en la primera fila de la ficha (2 columnas junto a la card de
// imagen; a las tres si el mazo no tiene imagen).
const descriptionHtml = computed(() => {
  const map = props.item.description ?? {}
  return (map[props.locale] || Object.values(map)[0] || '').trim()
})

function setTab(key: string) {
  tab.value = key as Tab
}

// El modo de juego enlaza al índice de mazos FILTRADO por ese modo (clave
// de query `mode`, la que lee el FactionDecksCatalog vía useFiltersQuery).
const gameModeRoute = computed(() =>
  props.item.game_mode
    ? sectionIndexRoute('decks', props.locale, { mode: String(props.item.game_mode.id) })
    : null,
)

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
// primera pestaña (héroes).
watch(
  () => props.item,
  async () => {
    tab.value = 'heroes'
    await nextTick()
    applyOgMeta({ image: props.item.image, type: 'article' })
  },
  { immediate: true },
)
</script>

<!-- eslint-disable vue/no-v-html -- HTML del wysiwyg propio, saneado en servidor -->
<template>
  <div class="deck-single single-sections">
    <!-- Ficha en GRID de tres columnas fijas con filas fijas (cortes
         explícitos a 2 → 1 en _entity-show.scss): fila 1 la card de IMAGEN
         (1 columna, solo si hay imagen) + la descripción (2 columnas; a
         las tres si no hay imagen); fila 2 información básica | coste de
         las cartas | tipos de cartas; fila 3 distribución de dados |
         clases | superclases. Al ancho wide de bloque -->
    <BlockShell :settings="{ width: 'wide', align: 'left' }">
      <div
        class="deck-detail-grid"
        :class="{ 'deck-detail-grid--with-media': item.image && descriptionHtml }"
      >
        <!-- Imagen del mazo (la imagen TAL CUAL, no la preview): card SOLO
             con la imagen llenándola, recortada al alto de la card de
             descripción (img absoluta + cover, _entity-show.scss) -->
        <InfoBlock v-if="item.image && descriptionHtml" class="deck-detail-grid__media">
          <img :src="item.image" :alt="name" />
        </InfoBlock>

        <!-- Descripción: tarjeta SIN título con el rich del wysiwyg -->
        <InfoBlock v-if="descriptionHtml" class="deck-detail-grid__description">
          <div class="rich-content" v-html="descriptionHtml" />
        </InfoBlock>

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
                <RouterLink v-if="gameModeRoute" class="info-link" :to="gameModeRoute">
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

        <InfoBlock v-if="stats.cards_by_type.length" :title="t('singles.deck.cardTypes')">
          <dl class="info-list">
            <template v-for="row in stats.cards_by_type" :key="row.name">
              <dt>{{ row.name }}</dt>
              <dd>{{ row.copies }}</dd>
            </template>
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

        <InfoBlock v-if="stats.heroes_by_class.length" :title="t('singles.deck.heroClasses')">
          <dl class="info-list">
            <template v-for="row in stats.heroes_by_class" :key="row.name">
              <dt>{{ row.name }}</dt>
              <dd>{{ row.count }}</dd>
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
      </div>
    </BlockShell>

    <!-- Pestañas héroes | cartas con contadores, al ancho de bloque -->
    <BlockShell :settings="{ width: 'wide', align: 'left' }">
      <BaseTabs
        class="deck-single__tabs"
        :tabs="tabs"
        :model-value="tab"
        @update:model-value="setTab"
      />

      <!-- Héroes del mazo (sin copias: asignado = 1, sin badge) -->
      <PreviewGrid
        v-if="tab === 'heroes'"
        :items="heroItems"
        :empty-text="t('singles.deck.noHeroes')"
        class="single-grid"
      >
        <template #item="{ item: hero }">
          <img
            v-if="hero.preview"
            class="preview-grid__image"
            :src="hero.preview"
            :alt="hero.name"
            loading="lazy"
          />
          <span v-else class="preview-grid__fallback">{{ hero.name }}</span>
        </template>
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
    </BlockShell>

    <!-- Cita épica: EXACTA al bloque quote del CRM (wide, centrada, fondo
         DINÁMICO Y OPACO del tema: token:surface-3 resuelto por BlockShell;
         surface pelado no contrastaba con la página en claro) -->
    <BlockQuote
      v-if="quoteHtml"
      :settings="{
        quote: quoteHtml,
        align: 'center',
        width: 'wide',
        background: 'token:surface-3',
      }"
    />

    <!-- Relateds de mazos (tarjetas CSS), excluyendo el actual: bloque
         related del CRM, con título (h2) y botón al índice -->
    <CssCardsRelated
      kind="deck"
      :exclude-id="item.id"
      :title="t('singles.deck.relatedTitle')"
      :button-label="t('singles.deck.relatedButton')"
    />
  </div>
</template>
