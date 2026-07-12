<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '@/lib/api'
import FactionCard from '@/components/FactionCard.vue'
import IndexFilters from '@/components/index/IndexFilters.vue'
import { useIndexPage } from '@/entities/indexPage'
import { useCatalogSort } from '@/entities/catalogSort'

// Índice público de facciones: tarjetas CSS con el color y el emblema de
// cada facción sobre GET /api/factions (pocas, sin paginar y sin búsqueda),
// con orden por toggles ('name' es el default histórico del endpoint; ?sort
// en la query string, fuente de verdad). Cada tarjeta enlaza a su single
// por el slug del locale activo.
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
const { locales, site, segment, section, canonicalize, applyHead } = useIndexPage()
const { sort, sortParam } = useCatalogSort('name')

const items = ref<FactionRow[]>([])
const loading = ref(true)

async function load() {
  if (!section.value || canonicalize()) return
  loading.value = true
  try {
    await site.load() // el head usa documentTitle: sin carreras en el prerender
    const { data } = await api.get('/factions', { params: { sort: sortParam.value } })
    items.value = data.data
  } catch {
    items.value = []
  } finally {
    loading.value = false
  }
  applyHead(t(section.value.titleKey))
}

// El orden llega por la query string (los toggles solo escriben en la URL).
watch([segment, () => locales.current, sort], load, { immediate: true })
</script>

<template>
  <main v-if="section" class="factions-index">
    <header class="factions-index__header">
      <h1 class="factions-index__title">{{ t(section.titleKey) }}</h1>
      <IndexFilters v-model:sort="sort" />
    </header>

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
