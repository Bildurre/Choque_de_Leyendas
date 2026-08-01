<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ArrowLeft } from '@lucide/vue'
import { PageBackground, useHead } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { sectionFor } from '@/entities/registry'
import AddToCollection from '@/components/AddToCollection.vue'
import IndexHeader from '@/components/IndexHeader.vue'
import { useLocalesStore } from '@/stores/locales'
import { useSiteStore } from '@/stores/site'

// Single público estándar (doc 10), inspirado en CDL, ya ENTERO en el
// lenguaje de bloques del CRM: la imagen de la entidad de fondo de página,
// el bloque header del CRM (IndexHeader: mismo markup que BlockHeader del
// motor) con SOLO el título (sin subtítulo), el «volver» dentro, la acción
// de añadir a la colección si la sección es coleccionable y el tinte que
// devuelva `blockHeader` del registry (p. ej. el color de la facción; null
// = gris por defecto); debajo, la ficha (el componente de detalle de la
// sección) apila secciones `block` que gestionan su propia anchura, igual
// que una página del CRM. El slug vale en cualquier locale y se redirige a
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

// Cabecera de bloque del CRM: su tinte de fondo sale del ítem ya cargado;
// null deja el gris por defecto de IndexHeader.
const headerBlock = computed(() =>
  item.value && section.value?.blockHeader ? section.value.blockHeader(item.value) : null,
)

/** La descripción sin HTML: solo alimenta la meta description del head. */
const description = computed(() => {
  const map = item.value?.description ?? {}
  const html = map[locales.current] || Object.values(map)[0] || ''
  return html
    .replace(/<[^>]*>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
})

/** La meta description SÍ se recorta (solo para el <head>, no se pinta). */
const metaDescription = computed(() =>
  description.value.length > 180 ? `${description.value.slice(0, 180)}…` : description.value,
)

// Token de petición: si mientras carga una entidad el usuario ya navegó a
// otra, la respuesta rezagada se descarta (no pisa a la vigente).
let requestId = 0

async function load() {
  if (!section.value) return
  const current = section.value
  const request = ++requestId
  failed.value = false

  try {
    await site.load() // el head usa documentTitle: sin carreras en el prerender
    const { data } = await api.get(`${current.endpoint}/${slug.value}`)
    if (request !== requestId) return
    item.value = data.data
  } catch {
    if (request !== requestId) return
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
    description: metaDescription.value || site.description || undefined,
    canonical: `${origin}/${locales.current}/${canonicalSection}/${canonicalSlug}`,
    alternates: Object.fromEntries(
      Object.entries(current.paths)
        .filter(([code]) => item.value?.slug[code])
        .map(([code, path]) => [code, `${origin}/${code}/${path}/${item.value?.slug[code]}`]),
    ),
  })
}

watch(
  [segment, slug, () => locales.current],
  ([newSegment, newSlug], old) => {
    // Al cambiar de ENTIDAD (segmento o slug) se retira YA el payload viejo:
    // la ruta cambia de sección al momento pero el fetch tarda, y el
    // componente de detalle nuevo no debe renderizar ni un tick con el ítem
    // de otra entidad (crash cruzado facción→héroe). El template cae al
    // estado sin ficha hasta que llega el payload correcto.
    if (!old || newSegment !== old[0] || newSlug !== old[1]) item.value = null
    load()
  },
  { immediate: true },
)
</script>

<template>
  <div v-if="section" class="entity-single">
    <template v-if="failed">
      <!-- Fondo configurable de la página de error (clave `errors` del mapa
           index_backgrounds): sustituye al de la entidad, que aquí no hay. -->
      <PageBackground :image="site.indexBackground('errors')" />
      <p class="entity-single__missing">{{ t('page.notFound') }}</p>
    </template>
    <template v-else-if="item">
      <!-- La imagen de la entidad, de fondo de página (patrón CDL) -->
      <PageBackground :image="(item.image as string) ?? null" />

      <!-- Header-bloque del CRM (solo título, volver dentro, tinte de la
           sección) y cuerpo a lo ancho — cada sección de la ficha es un
           `block` que gestiona su propia anchura, como en PageView. -->
      <main class="entity-single__body">
        <IndexHeader :title="name" :background="headerBlock?.tint || undefined">
          <template #top>
            <!-- Volver al índice: la página del CRM cuyo slug es el segmento -->
            <RouterLink
              class="entity-single__back"
              :to="{ name: 'page', params: { locale: locales.current, slug: segment } }"
            >
              <ArrowLeft :size="14" /> {{ t('detail.back') }}
            </RouterLink>
          </template>
          <AddToCollection
            v-if="section.collectible"
            :id="item.id"
            class="entity-single__action"
            :entity="section.collectible"
            label
          />
        </IndexHeader>
        <!-- Keyado por sección: al saltar de un single a otro (facción →
             héroe…) el componente de detalle se REMONTA limpio en vez de
             reutilizarse con estado de la entidad anterior. -->
        <component :is="section.detail" :key="section.key" :item="item" :locale="locales.current" />
      </main>
    </template>
  </div>
</template>
