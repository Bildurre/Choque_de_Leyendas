<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ArrowLeft } from '@lucide/vue'
import { PageBackground, useHead } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { sectionFor } from '@/entities/registry'
import AddToCollection from '@/components/AddToCollection.vue'
import { useLocalesStore } from '@/stores/locales'
import { useSiteStore } from '@/stores/site'

// Single público estándar (doc 10), inspirado en CDL: la imagen de la
// entidad de fondo de página, un BANNER con el nombre + subtítulo y la
// acción de añadir a la colección, y debajo la ficha (el componente de
// detalle de la sección). El slug vale en cualquier locale y se redirige a
// la canónica (DC-12).
interface EntityPayload {
  id: number
  name?: Record<string, string>
  description?: Record<string, string>
  image?: string | null
  slug: Record<string, string>
  [key: string]: unknown
}

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const locales = useLocalesStore()
const site = useSiteStore()

const item = ref<EntityPayload | null>(null)
const failed = ref(false)

const segment = computed(() => String(route.params.section ?? ''))
const slug = computed(() => String(route.params.slug ?? ''))
const section = computed(() => sectionFor(segment.value))

const name = computed(() => {
  const map = item.value?.name ?? {}
  return map[locales.current] || Object.values(map)[0] || ''
})

/** Subtítulo del banner: la descripción sin HTML, recortada. */
const subtitle = computed(() => {
  const map = item.value?.description ?? {}
  const html = map[locales.current] || Object.values(map)[0] || ''
  const text = html
    .replace(/<[^>]*>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
  return text.length > 180 ? `${text.slice(0, 180)}…` : text
})

async function load() {
  if (!section.value) return
  const current = section.value
  failed.value = false

  try {
    await site.load() // el head usa documentTitle: sin carreras en el prerender
    const { data } = await api.get(`${current.endpoint}/${slug.value}`)
    item.value = data.data
  } catch {
    failed.value = true
    return
  }

  // Canónica del locale activo: segmento y slug en el idioma de la URL.
  const canonicalSection = current.paths[locales.current] ?? segment.value
  const canonicalSlug = item.value?.slug[locales.current] || slug.value
  if (canonicalSection !== segment.value || canonicalSlug !== slug.value) {
    router.replace({
      params: { ...route.params, section: canonicalSection, slug: canonicalSlug },
    })
    return
  }

  const origin = window.location.origin
  useHead({
    title: site.documentTitle(name.value),
    description: subtitle.value || site.description || undefined,
    canonical: `${origin}/${locales.current}/${canonicalSection}/${canonicalSlug}`,
    alternates: Object.fromEntries(
      Object.entries(current.paths)
        .filter(([code]) => item.value?.slug[code])
        .map(([code, path]) => [code, `${origin}/${code}/${path}/${item.value?.slug[code]}`]),
    ),
  })
}

watch([segment, slug, () => locales.current], load, { immediate: true })
</script>

<template>
  <div v-if="section" class="entity-single">
    <p v-if="failed" class="entity-single__missing">{{ t('page.notFound') }}</p>
    <template v-else-if="item">
      <!-- La imagen de la entidad, de fondo de página (patrón CDL) -->
      <PageBackground :image="(item.image as string) ?? null" />

      <!-- Banner: volver + nombre + subtítulo, y la acción de añadir -->
      <header class="entity-single__banner">
        <div class="entity-single__banner-inner">
          <div class="entity-single__heading">
            <RouterLink
              class="entity-single__back"
              :to="{ name: 'entity-index', params: { locale: locales.current, section: segment } }"
            >
              <ArrowLeft :size="14" /> {{ t('detail.back') }}
            </RouterLink>
            <h1 class="entity-single__title">{{ name }}</h1>
            <p v-if="subtitle" class="entity-single__subtitle">{{ subtitle }}</p>
          </div>
          <AddToCollection
            v-if="section.collectible"
            :id="item.id"
            class="entity-single__action"
            :entity="section.collectible"
            label
          />
        </div>
      </header>

      <!-- La ficha: el componente de detalle de la sección -->
      <main class="entity-single__body">
        <component :is="section.detail" :item="item" :locale="locales.current" />
      </main>
    </template>
  </div>
</template>
