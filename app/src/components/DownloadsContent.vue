<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Download } from '@lucide/vue'
import { api } from '@/lib/api'
import CollectionManager from '@/components/CollectionManager.vue'
import { useLocalesStore } from '@/stores/locales'

// Contenido del apartado público de Descargas (doc 10, como en CDL),
// EXTRAÍDO de la vista (que queda como cáscara: canónica + SEO +
// IndexHeader) para que también lo monte el bloque «Descargas» del CRM: los
// PDF permanentes listos agrupados por tipo — para TODO el mundo, sin
// registro — y debajo "tu colección" para armar un PDF personalizado
// (también de invitado).
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

const groups = ref<DownloadGroup[]>([])
const loading = ref(true)

// Filtro de idioma de los PDF: por defecto, el idioma de la web (y se
// realinea si el visitante cambia de idioma). Solo se listan los del elegido.
const pdfLocale = ref(locales.current)
watch(
  () => locales.current,
  (code) => {
    pdfLocale.value = code
  },
)

const filteredGroups = computed(() =>
  groups.value
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => item.locale === pdfLocale.value),
    }))
    .filter((group) => group.items.length),
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

function typeLabel(type: string): string {
  return te(`downloads.types.${type}`) ? t(`downloads.types.${type}`) : type
}

function formatSize(bytes: number | null): string {
  if (!bytes) return ''
  if (bytes >= 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
  return `${(bytes / 1024).toFixed(0)} KB`
}
</script>

<template>
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
        <span class="downloads__name">{{ item.filename }}</span>
        <span class="downloads__meta">
          <span class="chip">{{ item.locale.toUpperCase() }}</span>
          <span v-if="item.size" class="downloads__size">{{ formatSize(item.size) }}</span>
        </span>
        <a class="downloads__link" :href="item.url" :title="t('collection.download')">
          <Download :size="18" />
        </a>
      </li>
    </ul>
  </section>

  <section class="downloads__collection">
    <h2>{{ t('collection.title') }}</h2>
    <p class="downloads__intro">{{ t('collection.intro') }}</p>
    <CollectionManager />
  </section>
</template>
