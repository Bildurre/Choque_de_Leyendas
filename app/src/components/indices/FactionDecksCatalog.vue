<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { IndexToolbar, MultiSelect } from '@edc-motor/ui'
import { api } from '@/lib/api'
import FactionDeckCard, { type FactionDeckCardData } from '@/components/FactionDeckCard.vue'
import CatalogFilters, { type CatalogSelection } from '@/components/indices/CatalogFilters.vue'
import type { EntitySection } from '@/entities/registry'
import { csvField, useFiltersQuery } from '@/entities/filtersQuery'
import { parseSort, type SortOption } from '@/entities/catalogSort'
import { useLocalesStore } from '@/stores/locales'

// Catálogo público de mazos EXTRAÍDO de la vista índice (que queda como
// cáscara: canónica + SEO + IndexHeader) para que también lo monte el bloque
// «Índice de entidad» del CRM: patrón unificado de los índices (IndexToolbar
// del motor: búsqueda multi-campo con debounce y toggles de orden) y los
// filtros de modo de juego y facción en CatalogFilters (chips encima de la
// búsqueda; selects siempre en el drawer derecho superpuesto del motor,
// abierto por su asa). Ambos son MULTISELECT (opciones de GET /api/faction-decks/filters;
// unión: la API filtra con whereIn y recibe `game_mode_id[]=` /
// `faction_id[]=`, y en la URL viajan como listas separadas por comas).
// Sin pestañas: salen SIEMPRE todos los mazos y cada tarjeta ya lleva el
// nombre de su modo. 'name' es el default histórico del endpoint. Todo vive
// en la query string (useFiltersQuery).
interface DeckRow extends FactionDeckCardData {
  id: number
  slug: string
}

interface FilterOption {
  id: number
  name: string
}

const props = defineProps<{ section: EntitySection }>()

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const locales = useLocalesStore()

const items = ref<DeckRow[]>([])
const loading = ref(true)

// Estado de los filtros: arrays de strings ([] = todos).
const search = ref('')
const modeIds = ref<string[]>([])
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
  fields: { mode: csvField(modeIds), faction: csvField(factionIds), sort: sortRaw },
})

// Opciones (localizadas por el server; se recargan por locale).
const modeOptions = ref<FilterOption[]>([])
const factionOptions = ref<FilterOption[]>([])

// Los modos pegados en la URL que ya no existan se limpian (los demás se
// quedan).
watch([modeIds, modeOptions], () => {
  if (!modeOptions.value.length || !modeIds.value.length) return
  const valid = modeIds.value.filter((id) =>
    modeOptions.value.some((option) => String(option.id) === id),
  )
  if (valid.length !== modeIds.value.length) modeIds.value = valid
})

// Opciones de MultiSelect: sin opción "todos" en la lista (sin nada
// marcado, el placeholder ya dice "Todos los modos" / "Todas las facciones").
function toSelect(options: FilterOption[]) {
  return options.map((option) => ({ value: String(option.id), label: option.name }))
}

const modeSelect = computed(() => toSelect(modeOptions.value))
const factionSelect = computed(() => toSelect(factionOptions.value))

// --- Chips de selección (CatalogFilters) ---
// Pares filtro+valor+label del estado vivo; el ORDEN cronológico global lo
// lleva CatalogFilters (registro de claves).
type ChipOptions = { value: string | number; label: string | number }[]

const chipSources = computed<Record<string, { values: string[]; options: ChipOptions }>>(() => ({
  mode: { values: modeIds.value, options: modeSelect.value },
  faction: { values: factionIds.value, options: factionSelect.value },
}))

const selections = computed<CatalogSelection[]>(() => {
  const out: CatalogSelection[] = []
  for (const [filter, { values, options }] of Object.entries(chipSources.value)) {
    for (const value of values) {
      const option = options.find((o) => String(o.value) === value)
      if (option) out.push({ filter, value, label: String(option.label) })
    }
  }
  return out
})

/** Quita UNA selección desde su chip (aspa). */
function removeSelection(filter: string, value: string) {
  const targets: Record<string, typeof modeIds> = { mode: modeIds, faction: factionIds }
  const target = targets[filter]
  if (target) target.value = target.value.filter((v) => v !== value)
}

// Segmento de la sección en el locale activo (los enlaces a cada single).
const segment = computed(
  () => props.section.paths[locales.current] ?? Object.values(props.section.paths)[0] ?? '',
)

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
  loading.value = true
  try {
    // Cada filtro viaja como array (`clave[]=`, serialización de axios);
    // vacío = no viaja (no filtra).
    const listParam = (values: string[]) => (values.length ? values : undefined)
    const { data } = await api.get('/faction-decks', {
      params: {
        search: search.value.trim() || undefined,
        game_mode_id: listParam(modeIds.value),
        faction_id: listParam(factionIds.value),
        sort: sort.value === 'name' ? undefined : sort.value,
      },
    })
    items.value = data.data
  } catch {
    items.value = []
  } finally {
    loading.value = false
  }
}

// "Quitar filtros" limpia SOLO los filtros (búsqueda y orden quedan).
function clearFilters() {
  modeIds.value = []
  factionIds.value = []
}

// El cambio de query ES el disparador de la recarga (también el botón atrás
// y las URLs pegadas); el cambio de idioma recarga con el locale nuevo (la
// cáscara que monta este catálogo se ocupa de la URL canónica).
watch(
  [() => locales.current, () => route.query],
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
  <!-- Chips de las elegidas encima de la búsqueda; los selects, en el
       drawer derecho superpuesto del motor (se abre con su asa, en todas
       las anchuras). Aplican en vivo, multivalor -->
  <CatalogFilters :selections="selections" @remove="removeSelection" @clear="clearFilters">
    <template #toolbar>
      <IndexToolbar
        v-model="search"
        v-model:sort="sort"
        :search-placeholder="t('catalog.searchPlaceholder')"
        :latest-label="t('catalog.sort.latest')"
        :oldest-label="t('catalog.sort.oldest')"
        :name-label="t('catalog.sort.nameAsc')"
        :name-desc-label="t('catalog.sort.nameDesc')"
      />
    </template>

    <MultiSelect
      v-model="modeIds"
      compact-trigger
      :label="t('catalog.filters.mode')"
      :placeholder="t('catalog.filters.allModes')"
      :options="modeSelect"
    />
    <MultiSelect
      v-model="factionIds"
      compact-trigger
      :label="t('catalog.filters.faction')"
      :placeholder="t('catalog.filters.allFactions')"
      :options="factionSelect"
    />
  </CatalogFilters>

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
</template>
