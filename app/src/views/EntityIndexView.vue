<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useHead } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { sectionFor } from '@/entities/registry'
import AddToCollection from '@/components/AddToCollection.vue'
import { useLocalesStore } from '@/stores/locales'
import { useSiteStore } from '@/stores/site'

// Índice público genérico de una entidad del juego (doc 10): resuelve la
// sección por el segmento de la URL, pide el listado al endpoint público y
// pinta la tarjeta que la sección declara. Cada tarjeta enlaza a su detalle.
interface EntityRow {
  id: number
  name?: Record<string, string>
  slug: Record<string, string>
  [key: string]: unknown
}

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const locales = useLocalesStore()
const site = useSiteStore()

const items = ref<EntityRow[]>([])
const loading = ref(true)

const segment = computed(() => String(route.params.section ?? ''))
const section = computed(() => sectionFor(segment.value))

async function load() {
  if (!section.value) return
  const current = section.value

  // Canónica del locale activo: /es/characters -> /es/personajes.
  const canonical = current.paths[locales.current] ?? segment.value
  if (canonical !== segment.value) {
    router.replace({ params: { ...route.params, section: canonical } })
    return
  }

  loading.value = true
  try {
    await site.load() // el head usa documentTitle: sin carreras en el prerender
    const { data } = await api.get(current.endpoint)
    items.value = data.data
  } catch {
    items.value = []
  } finally {
    loading.value = false
  }

  const origin = window.location.origin
  useHead({
    title: site.documentTitle(t(current.titleKey)),
    description: site.description || undefined,
    canonical: `${origin}/${locales.current}/${canonical}`,
    alternates: Object.fromEntries(
      Object.entries(current.paths).map(([code, path]) => [code, `${origin}/${code}/${path}`]),
    ),
  })
}

watch([segment, () => locales.current], load, { immediate: true })

function detailSlug(item: EntityRow): string {
  return item.slug[locales.current] || Object.values(item.slug)[0] || ''
}
</script>

<template>
  <main v-if="section" class="entity-index">
    <h1 class="entity-index__title">{{ t(section.titleKey) }}</h1>
    <p v-if="!loading && !items.length" class="entity-index__empty">{{ t('list.empty') }}</p>
    <div class="entity-index__grid">
      <div v-for="item in items" :key="item.id" class="entity-index__slot">
        <RouterLink
          class="entity-index__card-link"
          :to="{
            name: 'entity-detail',
            params: { locale: locales.current, section: segment, slug: detailSlug(item) },
          }"
        >
          <component :is="section.item" :item="item" :locale="locales.current" />
        </RouterLink>
        <!-- Añadir a la colección "para imprimir" (doc 02), como en CDL -->
        <AddToCollection
          v-if="section.collectible"
          :id="item.id"
          class="entity-index__add"
          :entity="section.collectible"
        />
      </div>
    </div>
  </main>
</template>
