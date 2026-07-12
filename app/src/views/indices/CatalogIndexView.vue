<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Search } from '@lucide/vue'
import { PreviewGrid, type CatalogItem, type PreviewGridItem } from '@edc-motor/ui'
import { api } from '@/lib/api'
import AddToCollection from '@/components/AddToCollection.vue'
import { useIndexPage } from '@/entities/indexPage'

// Índice de catálogo (cartas y héroes, CONVENTIONS2 §7.5): rejilla de
// previews sobre GET /api/catalog/{key} con búsqueda (debounce) y
// paginación. Cada ítem enlaza a su single por el slug del locale activo;
// al cambiar de idioma se recarga y se canoniza el segmento (slug-map).
const props = defineProps<{ catalogKey: string }>()

const { t } = useI18n()
const { locales, site, segment, section, canonicalize, applyHead } = useIndexPage()

const items = ref<PreviewGridItem[]>([])
const loading = ref(true)
const page = ref(1)
const pages = ref(0)
const total = ref(0)
const search = ref('')

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

async function load() {
  if (!section.value || canonicalize()) return
  loading.value = true
  try {
    await site.load() // el head usa documentTitle: sin carreras en el prerender
    const { data } = await api.get(`/catalog/${props.catalogKey}`, {
      params: { page: page.value, search: search.value.trim() || undefined },
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

// Búsqueda con debounce: resetea a la primera página.
let debounce: ReturnType<typeof setTimeout> | undefined
watch(search, () => {
  clearTimeout(debounce)
  debounce = setTimeout(() => {
    page.value = 1
    load()
  }, 350)
})
onBeforeUnmount(() => clearTimeout(debounce))

// El cambio de idioma dispara la canónica (load aborta y el nuevo segmento
// recarga con el locale nuevo), como en las vistas de página existentes.
watch(
  [segment, () => locales.current],
  () => {
    page.value = 1
    load()
  },
  { immediate: true },
)

function onPage(n: number) {
  page.value = n
  load()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
</script>

<template>
  <main v-if="section" class="catalog-index">
    <header class="catalog-index__header">
      <h1 class="catalog-index__title">{{ t(section.titleKey) }}</h1>
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
      <p v-if="!loading && items.length" class="catalog-index__count">
        {{ t('catalog.results', { count: total }, total) }}
      </p>
    </header>

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
