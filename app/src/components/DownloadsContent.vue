<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Download, Eye, FileText, Printer } from '@lucide/vue'
import { BaseTabs } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { formatDate, formatSize, inlineUrl } from '@/lib/format'
import CollectionManager from '@/components/CollectionManager.vue'
import { useCollectionStore } from '@/stores/collection'
import { useLocalesStore } from '@/stores/locales'

// Contenido del apartado público de Descargas (doc 10, como en CDL),
// EXTRAÍDO de la vista (que queda como cáscara: canónica + SEO +
// IndexHeader) para que también lo monte el bloque «Descargas» del CRM. En
// DOS PESTAÑAS (BaseTabs del motor, con icono: en estrecho colapsan a
// solo-icono): «PDFs» — los permanentes generados en admin, agrupados por
// tipo, para TODO el mundo sin registro — y «Mi colección» — el PDF
// personalizado (también de invitado). Cada card replica el pdf-item del
// CdL viejo: izquierda el título (con el chip de idioma pequeño delante) y
// debajo tamaño + fecha de creación en pequeño; derecha los botones de ver
// (abre el PDF EN PESTAÑA NUEVA sin descargarlo, ?inline=1 del motor) y
// descargar.
interface DownloadItem {
  id: number
  filename: string
  locale: string
  url: string
  size: number | null
  generated_at: string | null
}

interface DownloadGroup {
  type: string
  items: DownloadItem[]
}

const { t, te } = useI18n()
const locales = useLocalesStore()
const collection = useCollectionStore()

const groups = ref<DownloadGroup[]>([])
const loading = ref(true)

// Las dos pestañas; la de la colección enseña cuántos elementos lleva (el
// store se carga aquí para que el contador no espere a abrir la pestaña).
const activeTab = ref('pdfs')
const tabs = computed(() => [
  { key: 'pdfs', label: t('downloads.tabs.pdfs'), icon: FileText },
  {
    key: 'collection',
    label: t('downloads.tabs.collection'),
    icon: Printer,
    count: collection.items.length || undefined,
  },
])
if (!collection.loaded) collection.load()

// Filtro de idioma de los PDF: por defecto, el idioma de la web (y se
// realinea si el visitante cambia de idioma). Solo se listan los del elegido.
const pdfLocale = ref(locales.current)
watch(
  () => locales.current,
  (code) => {
    pdfLocale.value = code
  },
)

// Orden FIJO de las secciones (documentos, contadores, facciones, mazos,
// cartas, héroes — claves de los exports registrados en la API); un export
// nuevo que no esté en la lista cae al final, en el orden del endpoint.
const TYPE_ORDER = [
  'pages',
  'counters',
  'faction',
  'faction-deck',
  'cards-catalog',
  'heroes-catalog',
]

function typeRank(type: string): number {
  const index = TYPE_ORDER.indexOf(type)
  return index === -1 ? TYPE_ORDER.length : index
}

const filteredGroups = computed(() =>
  groups.value
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => item.locale === pdfLocale.value),
    }))
    .filter((group) => group.items.length)
    .sort((a, b) => typeRank(a.type) - typeRank(b.type)),
)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/downloads')
    groups.value = data.data
  } catch {
    groups.value = []
  } finally {
    loading.value = false
  }
}

load()

// Título de sección por clave de export (downloads.types.*); para un export
// nuevo sin traducción, fallback legible desde la clave (guiones a
// espacios, mayúscula inicial) en vez de la clave cruda.
function typeLabel(type: string): string {
  if (te(`downloads.types.${type}`)) return t(`downloads.types.${type}`)
  const readable = type.replace(/-/g, ' ')
  return readable.charAt(0).toUpperCase() + readable.slice(1)
}
</script>

<template>
  <BaseTabs v-model="activeTab" :tabs="tabs" />

  <template v-if="activeTab === 'pdfs'">
    <!-- Selector del idioma de los PDF (solo se listan los del elegido) -->
    <div class="downloads__filter" role="group" :aria-label="t('downloads.language')">
      <span class="downloads__filter-label">{{ t('downloads.language') }}</span>
      <button
        v-for="loc in locales.locales"
        :key="loc.code"
        type="button"
        class="downloads__filter-btn"
        :class="{ 'is-active': pdfLocale === loc.code }"
        @click="pdfLocale = loc.code"
      >
        {{ loc.code.toUpperCase() }}
      </button>
    </div>

    <p v-if="!loading && !filteredGroups.length" class="downloads__empty">
      {{ t('downloads.empty') }}
    </p>

    <section v-for="group in filteredGroups" :key="group.type" class="downloads__group">
      <h2>{{ typeLabel(group.type) }}</h2>
      <ul class="downloads__list">
        <li v-for="item in group.items" :key="item.id" class="downloads__item">
          <div class="downloads__header">
            <h3 class="downloads__name">
              <span class="chip downloads__chip">{{ item.locale.toUpperCase() }}</span>
              <span class="downloads__title">{{ item.filename }}</span>
            </h3>
            <p class="downloads__meta">
              <span v-if="item.size">{{ formatSize(item.size) }}</span>
              <span v-if="item.size && item.generated_at" class="downloads__sep" aria-hidden="true">
                •
              </span>
              <span v-if="item.generated_at">
                {{ formatDate(item.generated_at, locales.current) }}
              </span>
            </p>
          </div>
          <div class="downloads__actions">
            <a
              class="downloads__link"
              :href="inlineUrl(item.url)"
              target="_blank"
              rel="noopener"
              :title="t('downloads.view')"
              :aria-label="t('downloads.view')"
            >
              <Eye :size="18" />
            </a>
            <a
              class="downloads__link"
              :href="item.url"
              :title="t('downloads.download')"
              :aria-label="t('downloads.download')"
            >
              <Download :size="18" />
            </a>
          </div>
        </li>
      </ul>
    </section>
  </template>

  <CollectionManager v-else />
</template>
