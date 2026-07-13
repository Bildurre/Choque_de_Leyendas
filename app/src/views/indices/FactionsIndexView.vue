<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { IndexToolbar } from '@edc-motor/ui'
import { api } from '@/lib/api'
import FactionCard from '@/components/FactionCard.vue'
import { useIndexPage } from '@/entities/indexPage'
import { useFiltersQuery } from '@/entities/filtersQuery'
import { parseSort, type SortOption } from '@/entities/catalogSort'

// Índice público de facciones: tarjetas CSS con el color y el emblema de
// cada facción sobre GET /api/factions (pocas, sin paginar), con el patrón
// unificado de los índices (IndexToolbar del motor: búsqueda multi-campo
// con debounce y toggles de orden). No hay más campos que filtrar: la vista
// NO registra nada en la barra derecha contextual (el botón Funnel del
// header no aparece aquí). 'name' es el default histórico del endpoint.
// Todo vive en la query string (useFiltersQuery). Cada tarjeta enlaza a su
// single por el slug del locale activo.
interface FactionRow {
  id: number
  name: string
  slug: string
  color: string
  text_is_dark: boolean
  icon: string | null
  heroes_count: number
  cards_count: number
}

const { t } = useI18n()
const { route, router, locales, site, segment, section, canonicalize, applyHead } = useIndexPage()

const items = ref<FactionRow[]>([])
const loading = ref(true)

// Búsqueda (multi-campo en el servidor).
const search = ref('')

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
  fields: { sort: sortRaw },
})

async function load() {
  if (!section.value || canonicalize()) return
  loading.value = true
  try {
    await site.load() // el head usa documentTitle: sin carreras en el prerender
    const { data } = await api.get('/factions', {
      params: {
        search: search.value.trim() || undefined,
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
</script>

<template>
  <main v-if="section" class="factions-index">
    <header class="factions-index__header">
      <h1 class="factions-index__title">{{ t(section.titleKey) }}</h1>
    </header>

    <!-- Sin filtros que registrar: facciones solo busca y ordena -->
    <IndexToolbar
      v-model="search"
      v-model:sort="sort"
      :search-placeholder="t('catalog.searchPlaceholder')"
      :latest-label="t('catalog.sort.latest')"
      :oldest-label="t('catalog.sort.oldest')"
      :name-label="t('catalog.sort.nameAsc')"
      :name-desc-label="t('catalog.sort.nameDesc')"
    />

    <p v-if="loading" class="factions-index__loading" role="status">{{ t('catalog.loading') }}</p>
    <p v-else-if="!items.length" class="factions-index__empty">{{ t('list.empty') }}</p>

    <div v-else class="factions-index__grid">
      <RouterLink
        v-for="faction in items"
        :key="faction.id"
        class="factions-index__item"
        :to="{
          name: 'entity-detail',
          params: { locale: locales.current, section: segment, slug: faction.slug },
        }"
      >
        <FactionCard :faction="faction" />
      </RouterLink>
    </div>
  </main>
</template>
