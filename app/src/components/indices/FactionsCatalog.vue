<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { IndexToolbar } from '@edc-motor/ui'
import { api } from '@/lib/api'
import FactionCard from '@/components/FactionCard.vue'
import type { EntitySection } from '@/entities/registry'
import { useFiltersQuery } from '@/entities/filtersQuery'
import { parseSort, type SortOption } from '@/entities/catalogSort'
import { useLocalesStore } from '@/stores/locales'

// Catálogo público de facciones EXTRAÍDO de la vista índice (que queda como
// cáscara: canónica + SEO + IndexHeader) para que también lo monte el bloque
// «Índice de entidad» del CRM: tarjetas CSS con el color y el emblema de
// cada facción sobre GET /api/factions (pocas, sin paginar), con el patrón
// unificado de los índices (IndexToolbar del motor: búsqueda multi-campo
// con debounce y toggles de orden). No hay más campos que filtrar: no se
// registra nada en la barra derecha contextual (el botón Funnel del header
// no aparece aquí). 'name' es el default histórico del endpoint. Todo vive
// en la query string (useFiltersQuery). Cada tarjeta enlaza a su single por
// el slug del locale activo.
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

const props = defineProps<{ section: EntitySection }>()

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const locales = useLocalesStore()

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

// Segmento de la sección en el locale activo (los enlaces a cada single).
const segment = computed(
  () => props.section.paths[locales.current] ?? Object.values(props.section.paths)[0] ?? '',
)

async function load() {
  loading.value = true
  try {
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
</script>

<template>
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
</template>
