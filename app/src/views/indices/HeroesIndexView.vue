<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { FunnelX } from '@lucide/vue'
import {
  BaseButton,
  BasePagination,
  IndexToolbar,
  MultiSelect,
  PreviewGrid,
  useAppRightSidebar,
  type CatalogItem,
  type PreviewGridItem,
} from '@edc-motor/ui'
import { api } from '@/lib/api'
import AddToCollection from '@/components/AddToCollection.vue'
import IndexHeader from '@/components/IndexHeader.vue'
import { useIndexPage } from '@/entities/indexPage'
import { csvField, useFiltersQuery } from '@/entities/filtersQuery'
import { parseSort, type SortOption } from '@/entities/catalogSort'

// Índice público de héroes: rejilla de previews sobre GET /api/heroes con el
// patrón unificado de los índices (IndexToolbar del motor: búsqueda
// multi-campo con debounce y toggles de orden) y los filtros de
// facción/superclase/clase/raza en la barra derecha contextual
// (AppRightSidebar: registro + Teleport; el botón Funnel del header la
// despliega). Opciones ya localizadas de GET /api/heroes/filters, aplican
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

const { t } = useI18n()
const { route, router, locales, site, segment, section, canonicalize, applyHead } = useIndexPage()

const items = ref<PreviewGridItem[]>([])
const loading = ref(true)
const page = ref(1)
const pages = ref(0)
const total = ref(0)

// Filtros en la barra derecha contextual: se registra sin título (el
// cascarón pone el suyo, reactivo al locale) y se limpia al salir de la
// vista (el token evita pisar el registro de la vista entrante).
useAppRightSidebar().useRegister()

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

// Nº de filtros activos (enseña el "Quitar filtros" de la barra derecha;
// la búsqueda y el orden no cuentan).
const activeFilters = computed(
  () =>
    [factionIds.value, superclassIds.value, classIds.value, raceIds.value].filter(
      (values) => values.length > 0,
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
  applyHead(t(section.value.titleKey))
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
    <IndexHeader :title="t(section.titleKey)" :subtitle="t('entitiesIntro.heroes')" />

    <IndexToolbar
      v-model="search"
      v-model:sort="sort"
      :search-placeholder="t('catalog.searchPlaceholder')"
      :latest-label="t('catalog.sort.latest')"
      :oldest-label="t('catalog.sort.oldest')"
      :name-label="t('catalog.sort.nameAsc')"
      :name-desc-label="t('catalog.sort.nameDesc')"
    />

    <!-- Filtros en la barra derecha contextual (aplican en vivo, multivalor) -->
    <Teleport defer to="#app-right-sidebar-target">
      <MultiSelect
        v-model="factionIds"
        :label="t('catalog.filters.faction')"
        :placeholder="t('catalog.filters.allFactions')"
        :options="factionSelect"
      />
      <MultiSelect
        v-model="superclassIds"
        :label="t('catalog.filters.superclass')"
        :placeholder="t('catalog.filters.allSuperclasses')"
        :options="superclassSelect"
      />
      <MultiSelect
        v-model="classIds"
        :label="t('catalog.filters.class')"
        :placeholder="t('catalog.filters.allClasses')"
        :options="classSelect"
      />
      <MultiSelect
        v-model="raceIds"
        :label="t('catalog.filters.race')"
        :placeholder="t('catalog.filters.allRaces')"
        :options="raceSelect"
      />

      <!-- "Quitar filtros" (solo con filtros activos), como el pie del
           antiguo modal: la búsqueda y el orden se quedan como están -->
      <BaseButton v-if="activeFilters > 0" variant="secondary" type="button" @click="clearFilters">
        <template #icon><FunnelX :size="16" /></template>
        {{ t('catalog.filters.clear') }}
      </BaseButton>
    </Teleport>

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
