<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { FunnelX } from '@lucide/vue'
import { BaseButton, BaseTabs, IndexToolbar, MultiSelect, useAppRightSidebar } from '@edc-motor/ui'
import { api } from '@/lib/api'
import FactionDeckCard, { type FactionDeckCardData } from '@/components/FactionDeckCard.vue'
import IndexHeader from '@/components/IndexHeader.vue'
import { useIndexPage } from '@/entities/indexPage'
import { csvField, useFiltersQuery } from '@/entities/filtersQuery'
import { parseSort, type SortOption } from '@/entities/catalogSort'

// Índice público de mazos: patrón unificado de los índices (IndexToolbar
// del motor: búsqueda multi-campo con debounce y toggles de orden) +
// BaseTabs con una pestaña por modo de juego (de
// GET /api/faction-decks/filters, + "Todos") que alimenta game_mode_id en
// el servidor y se QUEDA en la vista; el filtro de facción va en la barra
// derecha contextual (AppRightSidebar: registro + Teleport; el botón Funnel
// del header la despliega) y es MULTISELECT: varias facciones a la vez
// (unión; la API filtra con whereIn y recibe `faction_id[]=`, y en la URL
// viaja como lista separada por comas). 'name' es el default histórico del
// endpoint.
// Cada tarjeta ya lleva el nombre de su modo, así que dentro de una pestaña
// no hace falta agrupar nada. Todo vive en la query string (useFiltersQuery).
interface DeckRow extends FactionDeckCardData {
  id: number
  slug: string
}

interface FilterOption {
  id: number
  name: string
}

const { t } = useI18n()
const { route, router, locales, site, segment, section, canonicalize, applyHead } = useIndexPage()

const items = ref<DeckRow[]>([])
const loading = ref(true)

// Filtros en la barra derecha contextual: se registra sin título (el
// cascarón pone el suyo, reactivo al locale) y se limpia al salir de la
// vista (el token evita pisar el registro de la vista entrante).
useAppRightSidebar().useRegister()

// Estado de los filtros ('' = todos; la facción es array, [] = todas).
const search = ref('')
const mode = ref('')
const factionIds = ref<string[]>([])

// Orden: 'name' es el default del índice (fuera de la URL; el endpoint sin
// ?sort ordena por nombre asc del locale).
const sortRaw = ref('')
const sort = computed<SortOption>({
  get: () => parseSort(sortRaw.value, 'name'),
  set: (value) => {
    sortRaw.value = value === 'name' ? '' : value
  },
})

// Estado <-> query string (URLs compartibles, botón atrás).
const { queryToState } = useFiltersQuery({
  route,
  router,
  search,
  fields: { mode, faction: csvField(factionIds), sort: sortRaw },
})

// Opciones (localizadas por el server; se recargan por locale).
const modeOptions = ref<FilterOption[]>([])
const factionOptions = ref<FilterOption[]>([])

// Pestañas: "Todos" + un modo de juego por pestaña.
const tabs = computed(() => [
  { key: 'all', label: t('catalog.filters.allModes') },
  ...modeOptions.value.map((option) => ({ key: String(option.id), label: option.name })),
])

const activeTab = computed({
  get: () => mode.value || 'all',
  set: (value: string) => {
    mode.value = value === 'all' ? '' : value
  },
})

// Un modo pegado en la URL que ya no exista cae a "Todos".
watch([mode, modeOptions], () => {
  if (!modeOptions.value.length || !mode.value) return
  if (!modeOptions.value.some((option) => String(option.id) === mode.value)) mode.value = ''
})

// Opciones del MultiSelect: sin opción "Todas" en la lista (sin nada
// marcado, el placeholder ya dice "Todas las facciones").
const factionSelect = computed(() =>
  factionOptions.value.map((option) => ({ value: String(option.id), label: option.name })),
)

// Nº de filtros activos (enseña el "Quitar filtros" de la barra derecha; la
// pestaña de modo, la búsqueda y el orden no cuentan).
const activeFilters = computed(() => (factionIds.value.length ? 1 : 0))

// --- Cargas ---

async function loadFilters() {
  try {
    const { data } = await api.get('/faction-decks/filters')
    const payload = data?.data ?? data ?? {}
    modeOptions.value = Array.isArray(payload.modes) ? payload.modes : []
    factionOptions.value = Array.isArray(payload.factions) ? payload.factions : []
  } catch {
    modeOptions.value = []
    factionOptions.value = []
  }
}

async function load() {
  if (!section.value || canonicalize()) return
  loading.value = true
  try {
    await site.load() // el head usa documentTitle: sin carreras en el prerender
    const { data } = await api.get('/faction-decks', {
      params: {
        search: search.value.trim() || undefined,
        game_mode_id: mode.value || undefined,
        // La facción viaja como array (`faction_id[]=`, serialización de
        // axios); vacío = no viaja (no filtra).
        faction_id: factionIds.value.length ? factionIds.value : undefined,
        sort: sort.value === 'name' ? undefined : sort.value,
      },
    })
    items.value = data.data
  } catch {
    items.value = []
  } finally {
    loading.value = false
  }
  applyHead(t(section.value.titleKey))
}

// "Quitar filtros" limpia SOLO los filtros (pestaña, búsqueda y orden quedan).
function clearFilters() {
  factionIds.value = []
}

// El cambio de query ES el disparador de la recarga (también el botón atrás
// y las URLs pegadas). El cambio de idioma dispara la canónica.
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
  <main v-if="section" class="decks-index">
    <IndexHeader :title="t(section.titleKey)" :subtitle="t('entitiesIntro.decks')" />

    <IndexToolbar
      v-model="search"
      v-model:sort="sort"
      :search-placeholder="t('catalog.searchPlaceholder')"
      :latest-label="t('catalog.sort.latest')"
      :oldest-label="t('catalog.sort.oldest')"
      :name-label="t('catalog.sort.nameAsc')"
      :name-desc-label="t('catalog.sort.nameDesc')"
    />

    <!-- Filtro de facción en la barra derecha contextual (aplica en vivo,
         multivalor) -->
    <Teleport defer to="#app-right-sidebar-target">
      <MultiSelect
        v-model="factionIds"
        :label="t('catalog.filters.faction')"
        :placeholder="t('catalog.filters.allFactions')"
        :options="factionSelect"
      />

      <!-- "Quitar filtros" (solo con filtros activos), como el pie del
           antiguo modal: pestaña, búsqueda y orden se quedan como están -->
      <BaseButton v-if="activeFilters > 0" variant="secondary" type="button" @click="clearFilters">
        <template #icon><FunnelX :size="16" /></template>
        {{ t('catalog.filters.clear') }}
      </BaseButton>
    </Teleport>

    <!-- Pestañas por modo de juego (server-side: game_mode_id), en la vista -->
    <BaseTabs v-if="modeOptions.length" v-model="activeTab" :tabs="tabs" />

    <p v-if="loading" class="decks-index__loading" role="status">{{ t('catalog.loading') }}</p>
    <p v-else-if="!items.length" class="decks-index__empty">{{ t('list.empty') }}</p>

    <div v-else class="decks-index__grid">
      <RouterLink
        v-for="deck in items"
        :key="deck.id"
        class="decks-index__item"
        :to="{
          name: 'entity-detail',
          params: { locale: locales.current, section: segment, slug: deck.slug },
        }"
      >
        <FactionDeckCard :deck="deck" />
      </RouterLink>
    </div>
  </main>
</template>
