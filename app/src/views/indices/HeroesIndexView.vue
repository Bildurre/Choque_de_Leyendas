<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { BaseSelect, PreviewGrid, type CatalogItem, type PreviewGridItem } from '@edc-motor/ui'
import { api } from '@/lib/api'
import AddToCollection from '@/components/AddToCollection.vue'
import IndexFilters from '@/components/index/IndexFilters.vue'
import { useIndexPage } from '@/entities/indexPage'
import { useFiltersQuery } from '@/entities/filtersQuery'
import { parseSort, type SortOption } from '@/entities/catalogSort'

// Índice público de héroes: rejilla de previews sobre GET /api/heroes con
// búsqueda (debounce), filtros de facción/superclase/clase/raza (opciones
// ya localizadas de GET /api/heroes/filters) y orden por toggles. Elegir
// superclase acota el select de clases a las suyas (client-side con el
// superclass_id que trae cada clase). Todo vive en la query string
// (useFiltersQuery): la UI empuja el estado a la URL y ES el cambio de
// query el que dispara la recarga.
interface FilterOption {
  id: number
  name: string
}

interface ClassOption extends FilterOption {
  superclass_id: number | null
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
const superclassId = ref('')
const classId = ref('')
const raceId = ref('')

// Orden: 'latest' es el default del índice (fuera de la URL).
const sortRaw = ref('')
const sort = computed<SortOption>({
  get: () => parseSort(sortRaw.value),
  set: (value) => {
    sortRaw.value = value === 'latest' ? '' : value
  },
})

// Estado <-> query string (URLs compartibles, botón atrás).
const { queryToState, pushQuery } = useFiltersQuery({
  route,
  router,
  search,
  page,
  fields: {
    faction: factionId,
    superclass: superclassId,
    class: classId,
    race: raceId,
    sort: sortRaw,
  },
})

// Opciones de los selects (localizadas por el server; se recargan por locale).
const factionOptions = ref<FilterOption[]>([])
const classOptions = ref<ClassOption[]>([])
const superclassOptions = ref<FilterOption[]>([])
const raceOptions = ref<FilterOption[]>([])

/** Opciones de BaseSelect con el "todos" delante. */
function withAll(options: FilterOption[], allLabel: string) {
  return [
    { value: '', label: allLabel },
    ...options.map((option) => ({ value: String(option.id), label: option.name })),
  ]
}

const factionSelect = computed(() =>
  withAll(factionOptions.value, t('catalog.filters.allFactions')),
)
const superclassSelect = computed(() =>
  withAll(superclassOptions.value, t('catalog.filters.allSuperclasses')),
)
const raceSelect = computed(() => withAll(raceOptions.value, t('catalog.filters.allRaces')))

// Con superclase elegida, el select de clases se acota a las suyas.
const visibleClasses = computed(() =>
  superclassId.value
    ? classOptions.value.filter((option) => String(option.superclass_id) === superclassId.value)
    : classOptions.value,
)
const classSelect = computed(() => withAll(visibleClasses.value, t('catalog.filters.allClasses')))

// Si la clase elegida deja de pertenecer a la superclase, se limpia.
watch([superclassId, classOptions], () => {
  if (!classOptions.value.length || !classId.value) return
  if (!visibleClasses.value.some((option) => String(option.id) === classId.value)) {
    classId.value = ''
  }
})

// Nº de filtros activos (badge del botón móvil y botón de limpiar).
const activeFilters = computed(
  () =>
    [factionId.value, superclassId.value, classId.value, raceId.value].filter(
      (value) => value !== '',
    ).length,
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
  if (!section.value || canonicalize()) return
  loading.value = true
  try {
    await site.load() // el head usa documentTitle: sin carreras en el prerender
    const { data } = await api.get('/heroes', {
      params: {
        page: page.value,
        search: search.value.trim() || undefined,
        faction_id: factionId.value || undefined,
        hero_superclass_id: superclassId.value || undefined,
        hero_class_id: classId.value || undefined,
        hero_race_id: raceId.value || undefined,
        sort: sort.value === 'latest' ? undefined : sort.value,
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

function clearFilters() {
  factionId.value = ''
  superclassId.value = ''
  classId.value = ''
  raceId.value = ''
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
  <main v-if="section" class="catalog-index heroes-index">
    <header class="catalog-index__header">
      <h1 class="catalog-index__title">{{ t(section.titleKey) }}</h1>
    </header>

    <IndexFilters
      v-model:search="search"
      v-model:sort="sort"
      :count="activeFilters"
      panel-id="heroes-filters"
      @clear="clearFilters"
    >
      <BaseSelect
        v-model="factionId"
        :label="t('catalog.filters.faction')"
        :options="factionSelect"
      />
      <BaseSelect
        v-model="superclassId"
        :label="t('catalog.filters.superclass')"
        :options="superclassSelect"
      />
      <BaseSelect v-model="classId" :label="t('catalog.filters.class')" :options="classSelect" />
      <BaseSelect v-model="raceId" :label="t('catalog.filters.race')" :options="raceSelect" />
    </IndexFilters>

    <p v-if="!loading && items.length" class="catalog-index__count">
      {{ t('catalog.results', { count: total }, total) }}
    </p>

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
