<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ArrowLeft, FileDown } from '@lucide/vue'
import { PageBackground, useHead } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { sectionFor } from '@/entities/registry'
import AddToCollection from '@/components/AddToCollection.vue'
import AdminEditButton from '@/components/AdminEditButton.vue'
import IndexHeader from '@/components/IndexHeader.vue'
import { useLocalesStore } from '@/stores/locales'
import { useSiteStore } from '@/stores/site'

// Single público estándar (doc 10), inspirado en CDL, ya ENTERO en el
// lenguaje de bloques del CRM: la imagen de la entidad de fondo de página,
// el bloque header del CRM (IndexHeader: mismo markup que BlockHeader del
// motor) con el título, el subtítulo que devuelva `blockHeader` del
// registry (el trasfondo en héroe/carta, la descripción en mazo, ya en
// texto plano; facción no lleva), la fila superior con el «volver» a la
// izquierda y la acción de añadir a la colección a la derecha si la
// sección es coleccionable, y el tinte que devuelva `blockHeader` (p. ej.
// el color de la facción; null = gris por defecto); debajo, la ficha (el
// componente de detalle de la sección) apila secciones `block` que
// gestionan su propia anchura, igual que una página del CRM. El slug vale
// en cualquier locale y se redirige a la canónica (DC-12).
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

// Cabecera de bloque del CRM: tinte de fondo y subtítulo salen del ítem ya
// cargado (y el locale activo); tinte null deja el gris por defecto de
// IndexHeader y subtítulo null/vacío no pinta el `block__subtitle`.
const headerBlock = computed(() =>
  item.value && section.value?.blockHeader
    ? section.value.blockHeader(item.value, locales.current)
    : null,
)

// PDF permanente de la entidad (facción/mazo lo exponen si su export está
// generado en el gestor del admin): botón de descarga en la fila superior.
const pdf = computed(() => (item.value?.pdf as { id: number; url: string } | null) ?? null)

// Slug del locale activo para el botón "editar en administración".
const itemSlug = computed(() => item.value?.slug[locales.current] ?? null)

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

      <!-- Header-bloque del CRM (título, subtítulo del registry, volver y
           añadir en la fila superior, tinte de la sección) y cuerpo a lo
           ancho — cada sección de la ficha es un `block` que gestiona su
           propia anchura, como en PageView. -->
      <main class="entity-single__body">
        <IndexHeader
          :title="name"
          :subtitle="headerBlock?.subtitle || undefined"
          :background="headerBlock?.tint || undefined"
        >
          <template #top>
            <!-- Fila superior: el «volver» a la izquierda y las acciones a
                 la derecha — añadir a la colección (secciones
                 coleccionables), descargar su PDF permanente (facción/mazo,
                 solo si está generado) y editar en administración (solo
                 editor/admin logueado, pestaña nueva). En estrecho los
                 botones pierden el texto por CSS y queda el icono, con su
                 aria-label/title -->
            <div class="entity-single__topbar">
              <!-- Volver al índice: la página del CRM cuyo slug es el segmento -->
              <RouterLink
                class="entity-single__back"
                :to="{ name: 'page', params: { locale: locales.current, slug: segment } }"
              >
                <ArrowLeft :size="14" /> {{ t('detail.back') }}
              </RouterLink>
              <div class="entity-single__actions">
                <AddToCollection
                  v-if="section.collectible"
                  :id="item.id"
                  class="entity-single__action"
                  :entity="section.collectible"
                  label
                />
                <!-- Descarga directa (Content-Disposition attachment) en
                     pestaña nueva: no se abandona la SPA -->
                <a
                  v-if="pdf"
                  class="entity-single__action entity-single__pdf"
                  :href="pdf.url"
                  target="_blank"
                  rel="noopener"
                  :title="t('detail.downloadPdf')"
                  :aria-label="t('detail.downloadPdf')"
                >
                  <FileDown :size="20" />
                  <span>{{ t('detail.downloadPdf') }}</span>
                </a>
                <AdminEditButton
                  :section="section.key"
                  :slug="itemSlug"
                  class="entity-single__action"
                  label
                />
              </div>
            </div>
          </template>
        </IndexHeader>
        <!-- Keyado por sección: al saltar de un single a otro (facción →
             héroe…) el componente de detalle se REMONTA limpio en vez de
             reutilizarse con estado de la entidad anterior. -->
        <component :is="section.detail" :key="section.key" :item="item" :locale="locales.current" />
      </main>
    </template>
  </div>
</template>
