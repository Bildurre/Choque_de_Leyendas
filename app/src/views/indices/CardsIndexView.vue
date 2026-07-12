<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Search, SlidersHorizontal } from '@lucide/vue'
import { PreviewGrid, type CatalogItem, type PreviewGridItem } from '@edc-motor/ui'
import { api } from '@/lib/api'
import AddToCollection from '@/components/AddToCollection.vue'
import { useIndexPage } from '@/entities/indexPage'

// Índice público de cartas con filtros avanzados: rejilla de previews sobre
// GET /api/cards (misma forma items+meta que el catálogo genérico) con
// búsqueda (debounce), select de facción y tipo (opciones ya localizadas de
// GET /api/cards/filters), nº de dados del coste (1..5 → cost_total) y
// colores del coste (toggles R/G/B → cost_colors tipo "RG"). Los filtros
// viven en la query
// string (URLs compartibles y botón atrás): la UI empuja el estado a la URL
// con router.replace y ES el cambio de query el que dispara la recarga.
// En móvil la barra de filtros se colapsa tras el botón "Filtros".
const COLORS = ['R', 'G', 'B'] as const
type CostColor = (typeof COLORS)[number]

interface FilterOption {
  id: number
  name: string
}

const { t } = useI18n()
const { route, router, locales, site, segment, section, canonicalize, applyHead } = useIndexPage()

const items = ref<PreviewGridItem[]>([])
const loading = ref(true)
const page = ref(1)
const pages = ref(0)
const total = ref(0)

// Estado de los filtros (los selects usan string: '' = todos).
const search = ref('')
const factionId = ref('')
const typeId = ref('')
const dice = ref('')
const colors = ref<CostColor[]>([])
const filtersOpen = ref(false)

// Opciones de los selects (localizadas por el server; se recargan por locale).
const factionOptions = ref<FilterOption[]>([])
const typeOptions = ref<FilterOption[]>([])

// Nº de filtros activos (badge del botón móvil y botón de limpiar).
const activeFilters = computed(
  () => [factionId.value, typeId.value, dice.value, colors.value.join('')].filter(Boolean).length,
)

function itemRoute(item: CatalogItem) {
  if (!item.slug || !section.value) return null
  return {
    name: 'entity-detail',
    params: {
      locale: locales.current,
      section: section.value.paths[locales.current] ?? segment.value,
      slug: item.slug,
    },
  }
}

// --- Sincronización estado <-> query string (URLs compartibles) ---

function stateToQuery(): Record<string, string> {
  const query: Record<string, string> = {}
  if (search.value.trim()) query.search = search.value.trim()
  if (factionId.value) query.faction = factionId.value
  if (typeId.value) query.type = typeId.value
  if (dice.value) query.dice = dice.value
  if (colors.value.length) query.colors = colors.value.join('')
  if (page.value > 1) query.page = String(page.value)
  return query
}

function queryToState() {
  const q = route.query
  const searchQ = typeof q.search === 'string' ? q.search : ''
  // No pisar el input mientras se escribe (la query guarda el valor sin espacios).
  if (search.value.trim() !== searchQ) search.value = searchQ
  factionId.value = typeof q.faction === 'string' ? q.faction : ''
  typeId.value = typeof q.type === 'string' ? q.type : ''
  const diceQ = Number(q.dice)
  dice.value = diceQ >= 1 && diceQ <= 5 ? String(diceQ) : ''
  const colorsQ = typeof q.colors === 'string' ? q.colors.toUpperCase() : ''
  const parsed = COLORS.filter((color) => colorsQ.includes(color))
  if (parsed.join('') !== colors.value.join('')) colors.value = parsed
  page.value = Math.max(1, Number(q.page) || 1)
}

/** true si el estado ya coincide con la query de la URL (nada que empujar). */
function inSyncWithUrl(): boolean {
  const target = stateToQuery()
  return ['search', 'faction', 'type', 'dice', 'colors', 'page'].every((key) => {
    const current = route.query[key]
    return (target[key] ?? '') === (typeof current === 'string' ? current : '')
  })
}

/** Empuja el estado a la URL; cambiar un filtro resetea a la página 1. */
function pushQuery(resetPage = true) {
  if (resetPage) page.value = 1
  router.replace({ query: stateToQuery() })
}

// --- Cargas ---

async function loadFilters() {
  try {
    const { data } = await api.get('/cards/filters')
    const payload = data?.data ?? data ?? {}
    factionOptions.value = Array.isArray(payload.factions) ? payload.factions : []
    typeOptions.value = Array.isArray(payload.types) ? payload.types : []
  } catch {
    factionOptions.value = []
    typeOptions.value = []
  }
}

async function load() {
  if (!section.value || canonicalize()) return
  loading.value = true
  try {
    await site.load() // el head usa documentTitle: sin carreras en el prerender
    const { data } = await api.get('/cards', {
      params: {
        page: page.value,
        search: search.value.trim() || undefined,
        faction_id: factionId.value || undefined,
        card_type_id: typeId.value || undefined,
        cost_total: dice.value || undefined,
        cost_colors: colors.value.join('') || undefined,
      },
    })
    items.value = (data.data as CatalogItem[]).map((item) => ({ ...item, to: itemRoute(item) }))
    page.value = data.meta.current_page
    pages.value = data.meta.last_page
    total.value = data.meta.total
  } catch {
    items.value = []
    pages.value = 0
    total.value = 0
  } finally {
    loading.value = false
  }
  applyHead(t(section.value.titleKey))
}

// --- Interacciones ---

// Búsqueda con debounce: al parar de teclear se empuja a la query.
let debounce: ReturnType<typeof setTimeout> | undefined
watch(search, (value) => {
  clearTimeout(debounce)
  if (value.trim() === (typeof route.query.search === 'string' ? route.query.search : '')) return
  debounce = setTimeout(() => pushQuery(), 350)
})
onBeforeUnmount(() => clearTimeout(debounce))

// Selects y toggles: a la query al momento (resetean a página 1). El guard
// evita re-empujar cuando el cambio viene de la propia URL (queryToState).
watch([factionId, typeId, dice, colors], () => {
  if (!inSyncWithUrl()) pushQuery()
})

function toggleColor(color: CostColor) {
  colors.value = colors.value.includes(color)
    ? colors.value.filter((c) => c !== color)
    : COLORS.filter((c) => c === color || colors.value.includes(c))
}

function clearFilters() {
  factionId.value = ''
  typeId.value = ''
  dice.value = ''
  colors.value = []
}

function onPage(n: number) {
  page.value = n
  pushQuery(false)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

// El cambio de query ES el disparador de la recarga (también el botón atrás
// y las URLs pegadas). El cambio de idioma dispara la canónica (load aborta
// y el nuevo segmento recarga con el locale nuevo).
watch(
  [segment, () => locales.current, () => route.query],
  () => {
    queryToState()
    load()
  },
  { immediate: true },
)

// Opciones de los filtros, por locale.
watch(() => locales.current, loadFilters, { immediate: true })
</script>

<template>
  <main v-if="section" class="catalog-index cards-index">
    <header class="catalog-index__header">
      <h1 class="catalog-index__title">{{ t(section.titleKey) }}</h1>

      <div class="cards-index__controls">
        <label class="catalog-index__search">
          <Search :size="16" class="catalog-index__search-icon" aria-hidden="true" />
          <input
            v-model="search"
            type="search"
            class="catalog-index__search-input"
            :placeholder="t('catalog.searchPlaceholder')"
            :aria-label="t('catalog.search')"
          />
        </label>

        <!-- En móvil los filtros se colapsan tras este botón -->
        <button
          type="button"
          class="cards-index__toggle"
          :aria-expanded="filtersOpen"
          aria-controls="cards-filters"
          @click="filtersOpen = !filtersOpen"
        >
          <SlidersHorizontal :size="16" aria-hidden="true" />
          {{ t('catalog.filters.toggle') }}
          <span v-if="activeFilters" class="cards-index__toggle-count">{{ activeFilters }}</span>
        </button>
      </div>

      <p v-if="!loading && items.length" class="catalog-index__count">
        {{ t('catalog.results', { count: total }, total) }}
      </p>
    </header>

    <!-- Barra de filtros (siempre visible en escritorio) -->
    <form
      id="cards-filters"
      class="cards-filters"
      :class="{ 'cards-filters--open': filtersOpen }"
      @submit.prevent
    >
      <label class="cards-filters__field">
        <span class="cards-filters__label">{{ t('catalog.filters.faction') }}</span>
        <select v-model="factionId" class="cards-filters__select">
          <option value="">{{ t('catalog.filters.allFactions') }}</option>
          <option v-for="option in factionOptions" :key="option.id" :value="String(option.id)">
            {{ option.name }}
          </option>
        </select>
      </label>

      <label class="cards-filters__field">
        <span class="cards-filters__label">{{ t('catalog.filters.type') }}</span>
        <select v-model="typeId" class="cards-filters__select">
          <option value="">{{ t('catalog.filters.allTypes') }}</option>
          <option v-for="option in typeOptions" :key="option.id" :value="String(option.id)">
            {{ option.name }}
          </option>
        </select>
      </label>

      <label class="cards-filters__field cards-filters__field--dice">
        <span class="cards-filters__label">{{ t('catalog.filters.dice') }}</span>
        <select v-model="dice" class="cards-filters__select">
          <option value="">{{ t('catalog.filters.anyDice') }}</option>
          <option v-for="n in 5" :key="n" :value="String(n)">
            {{ t('singles.deck.dice', { count: n }, n) }}
          </option>
        </select>
      </label>

      <fieldset class="cards-filters__colors">
        <legend class="cards-filters__label">{{ t('catalog.filters.colors') }}</legend>
        <div class="cards-filters__toggles">
          <button
            v-for="color in COLORS"
            :key="color"
            type="button"
            class="cards-filters__color"
            :class="[
              `cards-filters__color--${color.toLowerCase()}`,
              { 'is-active': colors.includes(color) },
            ]"
            :aria-pressed="colors.includes(color)"
            :aria-label="t(`catalog.filters.color${color}`)"
            :title="t(`catalog.filters.color${color}`)"
            @click="toggleColor(color)"
          >
            {{ color }}
          </button>
        </div>
      </fieldset>

      <button v-if="activeFilters" type="button" class="cards-filters__clear" @click="clearFilters">
        {{ t('catalog.filters.clear') }}
      </button>
    </form>

    <p v-if="loading && !items.length" class="catalog-index__loading" role="status">
      {{ t('catalog.loading') }}
    </p>
    <PreviewGrid
      v-else
      :items="items"
      :loading="loading"
      :page="page"
      :pages="pages"
      :empty-text="t('catalog.empty')"
      :prev-label="t('catalog.prev')"
      :next-label="t('catalog.next')"
      @page="onPage"
    >
      <!-- Añadir a la colección "para imprimir", flotante sobre la carta -->
      <template v-if="section.collectible" #actions="{ item }">
        <AddToCollection :id="item.id" class="catalog-index__add" :entity="section.collectible" />
      </template>
    </PreviewGrid>
  </main>
</template>
