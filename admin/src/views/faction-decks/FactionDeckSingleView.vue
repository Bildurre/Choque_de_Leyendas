<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { setActiveSlugMap } from '@/router'
import { ArrowLeft, Eye, EyeOff, SquarePen } from '@lucide/vue'
import { useResource } from '@edc-motor/admin-kit'
import { BaseButton, useToast } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { useLocalesStore } from '@/stores/locales'
import { usePageCrumb } from '@/composables/usePageCrumb'
import type { DeckGameModeRef, DeckPublishError, FactionDeck, Translations } from '@juego/shared'
import FactionDeckFormModal from '@/components/faction-decks/FactionDeckFormModal.vue'
import DeckHeroesModal from '@/components/faction-decks/DeckHeroesModal.vue'
import DeckCardsModal from '@/components/faction-decks/DeckCardsModal.vue'
import InfoBlock from '@/components/InfoBlock.vue'
import DashBarPanel, { type BarRow } from '@/components/dashboard/DashBarPanel.vue'

// Single de mazo como FICHA (patrón del single de facción): cabecera con
// límites del modo y contadores en vivo + avisos, cards de HÉROES y CARTAS
// (cada elemento enlazado a su single; la edición vive en modales que
// guardan ellos), trasfondo (descripción + cita épica) y ESTADÍSTICAS del
// contenido (cartas por tipo y curva de coste ponderadas por copias, héroes
// por clase y superclase) con las barras del dashboard.
const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const locales = useLocalesStore()
const { find } = useResource<FactionDeck>(api, '/admin/faction-decks')

const deck = ref<FactionDeck | null>(null)
const loading = ref(true)
const formOpen = ref(false)
const heroesOpen = ref(false)
const cardsOpen = ref(false)
const publishErrors = ref<DeckPublishError[]>([])

// Estadísticas del endpoint stats (nombres YA localizados por la API).
interface NamedCount {
  id: number
  name: string
  count: number
}
interface DeckStats {
  cards: { total: number; by_type: NamedCount[]; cost_curve: { dice: number; count: number }[] }
  heroes: { total: number; by_class: NamedCount[]; by_superclass: NamedCount[] }
}
const stats = ref<DeckStats | null>(null)

function tr(obj: Translations | null | undefined): string {
  return (
    obj?.[locales.current] || obj?.[locales.defaultLocale] || Object.values(obj || {})[0] || '—'
  )
}
const slug = computed(() => route.params.slug as string)

// Límites del modo del mazo: vienen embebidos en el show (game_mode).
const config = computed<DeckGameModeRef | null>(() => deck.value?.game_mode ?? null)

// Facciones del mazo: marcan lo asignado que sobra (aviso de abajo).
const deckFactionIds = computed(() => new Set((deck.value?.factions ?? []).map((f) => f.id)))

/** Slug localizado de un héroe/carta embebido (enlace a su single). */
function slugOf(item: { slug?: Translations }): string {
  return item.slug?.[locales.current] || Object.values(item.slug ?? {})[0] || ''
}

async function load() {
  loading.value = true
  try {
    deck.value = await find(slug.value)
    publishErrors.value = []
    setActiveSlugMap(deck.value?.slug ?? null) // DC-11: slug localizado al cambiar idioma
  } catch {
    deck.value = null
  } finally {
    loading.value = false
  }
}

async function loadStats() {
  try {
    const { data } = await api.get(`/admin/faction-decks/${slug.value}/stats`)
    stats.value = data.data
  } catch {
    stats.value = null
  }
}

// --- Contadores en vivo (las cartas suman copias; los héroes se cuentan,
// sin copias: cada uno vale 1). Salen del show: los modales guardan ellos
// y al emitir `saved` se recargan show + stats. ---
const cards = computed(() => deck.value?.cards ?? [])
const heroes = computed(() => deck.value?.heroes ?? [])
const totalCopies = computed(() => cards.value.reduce((sum, c) => sum + c.copies, 0))
const uniqueCards = computed(() => cards.value.length)
const totalHeroes = computed(() => heroes.value.length)

/** ¿El elemento pertenece a una facción que ya no está en el mazo? */
function isForeign(item: { faction_id: number | null }): boolean {
  return item.faction_id != null && !deckFactionIds.value.has(item.faction_id)
}
const foreignCount = computed(
  () => cards.value.filter(isForeign).length + heroes.value.filter(isForeign).length,
)

// Avisos de límite en cliente (mismas claves que valida el servidor al publicar)
const warnings = computed<DeckPublishError[]>(() => {
  const cfg = config.value
  if (!cfg) return []
  const out: DeckPublishError[] = []
  if (totalCopies.value < cfg.min_cards) {
    out.push({
      key: 'factionDecks.validation.minCards',
      params: { min: cfg.min_cards, total: totalCopies.value },
    })
  }
  if (totalCopies.value > cfg.max_cards) {
    out.push({
      key: 'factionDecks.validation.maxCards',
      params: { max: cfg.max_cards, total: totalCopies.value },
    })
  }
  const exceeded = cards.value.filter((c) => c.copies > cfg.max_copies_per_card).length
  if (exceeded > 0) {
    out.push({
      key: 'factionDecks.validation.maxCopies',
      params: { max: cfg.max_copies_per_card, count: exceeded },
    })
  }
  if (cfg.required_heroes > 0 && totalHeroes.value !== cfg.required_heroes) {
    out.push({
      key: 'factionDecks.validation.requiredHeroes',
      params: { required: cfg.required_heroes, total: totalHeroes.value },
    })
  }
  return out
})

// --- Estadísticas (mismos paneles de barras que el single de facción) ---
interface BarPanel {
  key: string
  title: string
  rows: BarRow[]
  max: number
}

function panel(key: string, title: string, rows: BarRow[]): BarPanel {
  return { key, title, rows, max: Math.max(...rows.map((r) => Number(r.count)), 1) }
}

const statTiles = computed(() =>
  stats.value
    ? [
        { key: 'cards', value: stats.value.cards.total },
        { key: 'heroes', value: stats.value.heroes.total },
      ]
    : [],
)

// Una gráfica por métrica (solo las que tienen filas), barras del dashboard.
const statPanels = computed<BarPanel[]>(() => {
  if (!stats.value) return []
  const rows = (items: NamedCount[]): BarRow[] =>
    items.map((row) => ({ key: row.id, label: row.name, count: row.count }))
  return [
    panel('cards-by-type', t('factions.stats.cardsByType'), rows(stats.value.cards.by_type)),
    panel('heroes-by-class', t('factions.stats.heroesByClass'), rows(stats.value.heroes.by_class)),
    panel(
      'heroes-by-superclass',
      t('factions.stats.heroesBySuperclass'),
      rows(stats.value.heroes.by_superclass),
    ),
  ].filter((p) => p.rows.length)
})

// Sin la columna de 5 dados: el coste real de las cartas llega hasta 4.
const costCurve = computed(() => (stats.value?.cards.cost_curve ?? []).filter((c) => c.dice <= 4))
const costCurveMax = computed(() => Math.max(...costCurve.value.map((c) => c.count), 1))

function pct(count: number, max: number): string {
  return `${(count / Math.max(max, 1)) * 100}%`
}

/** Publicar valida en servidor: el 422 trae errors.deck localizable. */
async function togglePublished() {
  if (!deck.value) return
  publishErrors.value = []
  try {
    const { data } = await api.post(`/admin/faction-decks/${slug.value}/toggle-published`)
    const wasPublished = deck.value.is_published
    deck.value = { ...deck.value, ...data.data }
    toast.success(
      wasPublished ? t('factionDecks.toast.unpublished') : t('factionDecks.toast.published'),
    )
  } catch (e) {
    const errs = (e as { response?: { data?: { errors?: { deck?: DeckPublishError[] } } } })
      ?.response?.data?.errors?.deck
    if (Array.isArray(errs) && errs.length) {
      publishErrors.value = errs
      toast.danger(t('factionDecks.toast.publishInvalid'))
    } else {
      toast.danger(t('common.errors.action'))
    }
  }
}

// Tras guardar cualquier modal: recarga el show (contadores y avisos se
// recalculan con lo persistido) y las estadísticas.
async function onSaved() {
  await Promise.all([load(), loadStats()])
}

onMounted(async () => {
  await locales.load()
  await Promise.all([load(), loadStats()])
})

// Los nombres de las estadísticas llegan localizados: se piden de nuevo al
// cambiar el idioma de contenido.
watch(
  () => locales.current,
  () => loadStats(),
)

// El nombre del single como último tramo de la breadcrumb (se actualiza si
// cambia el locale de contenido) y fuera al salir de la vista.
const crumb = usePageCrumb()
watch(
  [deck, () => locales.current],
  () => {
    if (deck.value) crumb.set(tr(deck.value.name))
  },
  { immediate: true },
)
onBeforeUnmount(() => {
  crumb.clear()
  setActiveSlugMap(null)
})
</script>

<!-- eslint-disable vue/no-v-html -- HTML del WYSIWYG propio (sanitización en servidor) -->
<template>
  <div v-if="deck" class="single deck-single">
    <div class="single__bar">
      <BaseButton variant="text" @click="router.push({ name: 'faction-decks' })">
        <template #icon><ArrowLeft :size="16" /></template>
        {{ t('factionDecks.title') }}
      </BaseButton>
      <div class="deck-single__bar-actions">
        <BaseButton variant="info" @click="formOpen = true">
          <template #icon><SquarePen :size="16" /></template>
          {{ t('common.actions.edit') }}
        </BaseButton>
        <BaseButton variant="warning" @click="togglePublished">
          <template #icon>
            <component :is="deck.is_published ? EyeOff : Eye" :size="16" />
          </template>
          {{ deck.is_published ? t('common.actions.unpublish') : t('common.actions.publish') }}
        </BaseButton>
      </div>
    </div>

    <!-- Cabecera: nombre + límites del modo + contadores en vivo -->
    <header class="deck-single__header">
      <div class="deck-single__identity">
        <div class="deck-single__emblem">
          <img v-if="deck.image" :src="deck.image" alt="" />
          <span v-else class="deck-single__mono">{{ tr(deck.name).charAt(0) }}</span>
        </div>
        <div>
          <h1>{{ tr(deck.name) }}</h1>
          <!-- Sin chips en los singles (regla transversal): texto coloreado -->
          <p class="deck-single__meta-line">
            <span v-if="deck.is_published" class="deck-single__state is-published">{{
              t('factionDecks.state.published')
            }}</span>
            <span v-else class="deck-single__state">{{ t('factionDecks.state.draft') }}</span>
            <span v-if="deck.game_mode">{{ tr(deck.game_mode.name) }}</span>
            <!-- Texto en el color del tema; la identidad, en la muestra. -->
            <span v-for="faction in deck.factions ?? []" :key="faction.id">
              <span v-if="faction.color" class="swatch" :style="{ background: faction.color }" />{{
                tr(faction.name)
              }}
            </span>
          </p>
        </div>
      </div>

      <dl class="deck-single__stats">
        <div v-if="config">
          <dt>{{ t('factionDecks.single.modeLimits') }}</dt>
          <dd>
            {{
              t('factionDecks.single.limitsLine', {
                min: config.min_cards,
                max: config.max_cards,
                copies: config.max_copies_per_card,
                heroes: config.required_heroes,
              })
            }}
          </dd>
        </div>
        <div>
          <dt>{{ t('factionDecks.single.cardsCount') }}</dt>
          <dd>
            {{ totalCopies }}
            <small>({{ t('factionDecks.single.uniqueCards', { count: uniqueCards }) }})</small>
          </dd>
        </div>
        <div>
          <dt>{{ t('factionDecks.single.heroesCount') }}</dt>
          <dd>{{ totalHeroes }}</dd>
        </div>
      </dl>
    </header>

    <!-- Errores de publicación del servidor (bloquean publicar, no guardar) -->
    <ul v-if="publishErrors.length" class="deck-single__errors">
      <li v-for="(err, i) in publishErrors" :key="i">{{ t(err.key, err.params ?? {}) }}</li>
    </ul>

    <!-- Avisos de límite en vivo (no bloquean: el servidor decide al publicar) -->
    <ul v-if="warnings.length || foreignCount > 0" class="deck-single__warnings">
      <li v-for="(w, i) in warnings" :key="i">{{ t(w.key, w.params ?? {}) }}</li>
      <!-- Asignados de facciones que ya no están en el mazo -->
      <li v-if="foreignCount > 0">
        {{ t('factionDecks.single.factionMismatch', { count: foreignCount }) }}
      </li>
    </ul>

    <!-- Cards de contenido: héroes y cartas del mazo, cada elemento enlazado
         a su single; el botón Editar de cada card abre su modal -->
    <div class="deck-single__cards">
      <InfoBlock :title="`${t('factionDecks.single.heroesTitle')} (${totalHeroes})`">
        <template #actions>
          <BaseButton variant="info" @click="heroesOpen = true">
            <template #icon><SquarePen :size="14" /></template>
            {{ t('common.actions.edit') }}
          </BaseButton>
        </template>
        <p v-if="!heroes.length" class="deck-single__empty">
          {{ t('factionDecks.single.noHeroes') }}
        </p>
        <ul v-else class="deck-single__list">
          <li v-for="hero in heroes" :key="hero.id">
            <RouterLink
              class="hero-link deck-single__item-name"
              :to="{ name: 'hero-single', params: { slug: slugOf(hero) } }"
              >{{ tr(hero.name) }}</RouterLink
            >
          </li>
        </ul>
      </InfoBlock>

      <InfoBlock :title="`${t('factionDecks.single.cardsTitle')} (${totalCopies})`">
        <template #actions>
          <BaseButton variant="info" @click="cardsOpen = true">
            <template #icon><SquarePen :size="14" /></template>
            {{ t('common.actions.edit') }}
          </BaseButton>
        </template>
        <p v-if="!cards.length" class="deck-single__empty">
          {{ t('factionDecks.single.noCards') }}
        </p>
        <ul v-else class="deck-single__list">
          <li v-for="card in cards" :key="card.id">
            <RouterLink
              class="hero-link deck-single__item-name"
              :to="{ name: 'card-single', params: { slug: slugOf(card) } }"
              >{{ tr(card.name) }}</RouterLink
            >
            <span v-if="card.copies > 1" class="deck-single__item-copies">×{{ card.copies }}</span>
          </li>
        </ul>
      </InfoBlock>
    </div>

    <!-- Trasfondo (como en facción): descripción + cita épica del wysiwyg -->
    <template v-if="tr(deck.description) !== '—' || tr(deck.epic_quote) !== '—'">
      <h2 class="single__section">{{ t('factionDecks.sections.lore') }}</h2>
      <div class="rich-content" v-html="tr(deck.description)" />
      <blockquote
        v-if="tr(deck.epic_quote) !== '—'"
        class="deck-single__quote rich-content"
        v-html="tr(deck.epic_quote)"
      />
    </template>

    <!-- Estadísticas del contenido del mazo (barras del dashboard) -->
    <template v-if="stats">
      <h2 class="single__section">{{ t('factionDecks.sections.stats') }}</h2>
      <div class="dashboard__tiles dashboard__tiles--sub">
        <article v-for="tile in statTiles" :key="tile.key" class="dash-tile">
          <span class="dash-tile__value">{{ tile.value }}</span>
          <span class="dash-tile__label">{{ t(`factions.counts.${tile.key}`) }}</span>
        </article>
      </div>
      <div class="dashboard__grid">
        <!-- Curva de coste de sus cartas (en copias): columnas por nº de dados -->
        <article v-if="stats.cards.total" class="dash-panel">
          <h3 class="dash-panel__title">{{ t('factions.stats.costCurve') }}</h3>
          <div class="dash-curve">
            <div v-for="col in costCurve" :key="col.dice" class="dash-curve__col">
              <span class="dash-curve__count">{{ col.count }}</span>
              <span class="dash-curve__track">
                <span class="dash-curve__fill" :style="{ height: pct(col.count, costCurveMax) }" />
              </span>
              <span class="dash-curve__label">{{ col.dice }}</span>
            </div>
          </div>
        </article>
        <DashBarPanel
          v-for="p in statPanels"
          :key="p.key"
          :title="p.title"
          :rows="p.rows"
          :max="p.max"
        />
      </div>
    </template>

    <FactionDeckFormModal v-model="formOpen" mode="edit" :target-slug="slug" @saved="onSaved" />
    <DeckHeroesModal v-model="heroesOpen" :deck="deck" @saved="onSaved" />
    <DeckCardsModal v-model="cardsOpen" :deck="deck" @saved="onSaved" />
  </div>
  <p v-else-if="!loading" class="single__empty">{{ t('common.empty') }}</p>
</template>
