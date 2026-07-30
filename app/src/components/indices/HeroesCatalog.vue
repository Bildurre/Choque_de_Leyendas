<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  BasePagination,
  IndexToolbar,
  MultiSelect,
  PreviewGrid,
  type CatalogItem,
  type PreviewGridItem,
} from '@edc-motor/ui'
import { api } from '@/lib/api'
import AddToCollection from '@/components/AddToCollection.vue'
import CatalogFilters, { type CatalogSelection } from '@/components/indices/CatalogFilters.vue'
import type { EntitySection } from '@/entities/registry'
import { csvField, useFiltersQuery } from '@/entities/filtersQuery'
import { parseSort, type SortOption } from '@/entities/catalogSort'
import { useLocalesStore } from '@/stores/locales'

// Catálogo público de héroes EXTRAÍDO de la vista índice (que queda como
// cáscara: canónica + SEO + IndexHeader) para que también lo monte el bloque
// «Índice de entidad» del CRM: rejilla de previews sobre GET /api/heroes con
// el patrón unificado de los índices (IndexToolbar del motor: búsqueda
// multi-campo con debounce y toggles de orden) y los filtros de
// facción/superclase/clase/raza en CatalogFilters (en ancho, panel
// plegable bajo la búsqueda; en estrecho, la barra derecha off-canvas del
// motor). Opciones ya localizadas de GET /api/heroes/filters, aplican
// en vivo y son MULTISELECT: cada filtro admite varios valores (unión; la
// API filtra con whereIn y recibe `clave[]=`). Marcar superclases acota el
// select de clases a las de CUALQUIERA de ellas (client-side con el
// superclass_id que trae cada clase). Todo vive en la query string
// (useFiltersQuery, listas separadas por comas): la UI empuja el estado a
// la URL y ES el cambio de query el que dispara la recarga.
interface FilterOption {
  id: number
  name: string
}

interface ClassOption extends FilterOption {
  superclass_id: number | null
}

const props = defineProps<{ section: EntitySection }>()

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const locales = useLocalesStore()

const items = ref<PreviewGridItem[]>([])
const loading = ref(true)
const page = ref(1)
const pages = ref(0)
const total = ref(0)

// Estado de los filtros: arrays de strings ([] = todos), lo que edita cada
// MultiSelect; en la URL viajan como listas separadas por comas (csvField).
const search = ref('')
const factionIds = ref<string[]>([])
const superclassIds = ref<string[]>([])
const classIds = ref<string[]>([])
const raceIds = ref<string[]>([])

// Orden: 'name' (alfabético del locale activo) es el default del índice
// (fuera de la URL).
const sortRaw = ref('')
const sort = computed<SortOption>({
  get: () => parseSort(sortRaw.value),
  set: (value) => {
    sortRaw.value = value === 'name' ? '' : value
  },
})

// Estado <-> query string (URLs compartibles, botón atrás).
const { queryToState, pushQuery } = useFiltersQuery({
  route,
  router,
  search,
  page,
  fields: {
    faction: csvField(factionIds),
    superclass: csvField(superclassIds),
    class: csvField(classIds),
    race: csvField(raceIds),
    sort: sortRaw,
  },
})

// Opciones de los selects (localizadas por el server; se recargan por locale).
const factionOptions = ref<FilterOption[]>([])
const classOptions = ref<ClassOption[]>([])
const superclassOptions = ref<FilterOption[]>([])
const raceOptions = ref<FilterOption[]>([])

// Opciones de MultiSelect: sin opción "todos" en la lista (sin nada
// marcado, el placeholder ya dice "Todas las …").
function toSelect(options: FilterOption[]) {
  return options.map((option) => ({ value: String(option.id), label: option.name }))
}

const factionSelect = computed(() => toSelect(factionOptions.value))
const superclassSelect = computed(() => toSelect(superclassOptions.value))
const raceSelect = computed(() => toSelect(raceOptions.value))

// Con superclases marcadas, el select de clases se acota a las de
// CUALQUIERA de ellas.
const visibleClasses = computed(() =>
  superclassIds.value.length
    ? classOptions.value.filter((option) =>
        superclassIds.value.includes(String(option.superclass_id)),
      )
    : classOptions.value,
)
const classSelect = computed(() => toSelect(visibleClasses.value))

// Las clases marcadas que dejen de pertenecer a alguna superclase marcada
// se limpian (las demás se quedan).
watch([superclassIds, classOptions], () => {
  if (!classOptions.value.length || !classIds.value.length) return
  const valid = classIds.value.filter((id) =>
    visibleClasses.value.some((option) => String(option.id) === id),
  )
  if (valid.length !== classIds.value.length) classIds.value = valid
})

// Nº de filtros activos (badge del botón «Filtros» y visibilidad del
// "Quitar filtros"; la búsqueda y el orden no cuentan).
const activeFilters = computed(
  () =>
    [factionIds.value, superclassIds.value, classIds.value, raceIds.value].filter(
      (values) => values.length > 0,
    ).length,
)

// --- Chips de selección (CatalogFilters) ---
// Pares filtro+valor+label del estado vivo; el ORDEN cronológico global lo
// lleva CatalogFilters (registro de claves).
type ChipOptions = { value: string | number; label: string | number }[]

const chipSources = computed<Record<string, { values: string[]; options: ChipOptions }>>(() => ({
  faction: { values: factionIds.value, options: factionSelect.value },
  superclass: { values: superclassIds.value, options: superclassSelect.value },
  class: { values: classIds.value, options: classSelect.value },
  race: { values: raceIds.value, options: raceSelect.value },
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
  const targets: Record<string, typeof factionIds> = {
    faction: factionIds,
    superclass: superclassIds,
    class: classIds,
    race: raceIds,
  }
  const target = targets[filter]
  if (target) target.value = target.value.filter((v) => v !== value)
}

function itemRoute(item: CatalogItem) {
  const segment = props.section.paths[locales.current] ?? Object.values(props.section.paths)[0]
  if (!item.slug || !segment) return null
  return {
    name: 'entity-detail',
    params: { locale: locales.current, section: segment, slug: item.slug },
  }
}

// --- Cargas ---

async function loadFilters() {
  try {
    const { data } = await api.get('/heroes/filters')
    const payload = data?.data ?? data ?? {}
    factionOptions.value = Array.isArray(payload.factions) ? payload.factions : []
    classOptions.value = Array.isArray(payload.classes) ? payload.classes : []
    superclassOptions.value = Array.isArray(payload.superclasses) ? payload.superclasses : []
    raceOptions.value = Array.isArray(payload.races) ? payload.races : []
  } catch {
    factionOptions.value = []
    classOptions.value = []
    superclassOptions.value = []
    raceOptions.value = []
  }
}

async function load() {
  loading.value = true
  try {
    // Cada filtro viaja como array (`clave[]=`, serialización de axios);
    // vacío = no viaja (no filtra).
    const listParam = (values: string[]) => (values.length ? values : undefined)
    const { data } = await api.get('/heroes', {
      params: {
        page: page.value,
        search: search.value.trim() || undefined,
        faction_id: listParam(factionIds.value),
        hero_superclass_id: listParam(superclassIds.value),
        hero_class_id: listParam(classIds.value),
        hero_race_id: listParam(raceIds.value),
        sort: sort.value === 'name' ? undefined : sort.value,
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
}

// --- Interacciones ---

// "Quitar filtros" limpia SOLO los filtros (la búsqueda y el orden quedan).
function clearFilters() {
  factionIds.value = []
  superclassIds.value = []
  classIds.value = []
  raceIds.value = []
}

function onPage(n: number) {
  page.value = n
  pushQuery(false)
  window.scrollTo({ top: 0, behavior: 'smooth' })
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
  <!-- Chips de las elegidas encima de la búsqueda; el botón «Filtros» a la
       derecha del orden (misma fila); panel suelto debajo en ancho y barra
       derecha off-canvas en estrecho. Aplican en vivo, multivalor -->
  <CatalogFilters
    :active-count="activeFilters"
    :selections="selections"
    @remove="removeSelection"
    @clear="clearFilters"
  >
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
      v-model="factionIds"
      compact-trigger
      :label="t('catalog.filters.faction')"
      :placeholder="t('catalog.filters.allFactions')"
      :options="factionSelect"
    />
    <MultiSelect
      v-model="superclassIds"
      compact-trigger
      :label="t('catalog.filters.superclass')"
      :placeholder="t('catalog.filters.allSuperclasses')"
      :options="superclassSelect"
    />
    <MultiSelect
      v-model="classIds"
      compact-trigger
      :label="t('catalog.filters.class')"
      :placeholder="t('catalog.filters.allClasses')"
      :options="classSelect"
    />
    <MultiSelect
      v-model="raceIds"
      compact-trigger
      :label="t('catalog.filters.race')"
      :placeholder="t('catalog.filters.allRaces')"
      :options="raceSelect"
    />
  </CatalogFilters>

  <BasePagination
    class="catalog-index__pagination"
    :page="page"
    :pages="pages"
    :prev-label="t('catalog.pagination.prev')"
    :next-label="t('catalog.pagination.next')"
    :of-label="t('catalog.pagination.of', { page, pages })"
    @update:page="onPage"
  />

  <p v-if="!loading && items.length" class="catalog-index__count">
    {{ t('catalog.results', { count: total }, total) }}
  </p>

  <p v-if="loading && !items.length" class="catalog-index__loading" role="status">
    {{ t('catalog.loading') }}
  </p>
  <PreviewGrid v-else :items="items" :loading="loading" :empty-text="t('catalog.empty')">
    <!-- Añadir a la colección "para imprimir", flotante sobre la carta -->
    <template v-if="section.collectible" #actions="{ item }">
      <AddToCollection :id="item.id" class="catalog-index__add" :entity="section.collectible" />
    </template>
  </PreviewGrid>

  <!-- Paginación inferior: gemela de la superior (mismas clases/props) -->
  <BasePagination
    class="catalog-index__pagination"
    :page="page"
    :pages="pages"
    :prev-label="t('catalog.pagination.prev')"
    :next-label="t('catalog.pagination.next')"
    :of-label="t('catalog.pagination.of', { page, pages })"
    @update:page="onPage"
  />
</template>
