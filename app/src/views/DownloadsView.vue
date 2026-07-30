<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useHead } from '@edc-motor/ui'
import { DOWNLOAD_PATHS } from '@/router/downloads'
import DownloadsContent from '@/components/DownloadsContent.vue'
import IndexHeader from '@/components/IndexHeader.vue'
import { useLocalesStore } from '@/stores/locales'
import { useSiteStore } from '@/stores/site'

// Apartado público de Descargas (doc 10, como en CDL): CÁSCARA (canónica
// del locale + SEO + IndexHeader). El interior — los PDF permanentes
// agrupados por tipo y "tu colección" — vive en DownloadsContent,
// compartido con el bloque «Descargas» del CRM.
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const locales = useLocalesStore()
const site = useSiteStore()

const segment = computed(() => String(route.params.dl ?? ''))

async function applyHead() {
  // Canónica del locale activo: /es/downloads -> /es/descargas.
  const canonical = DOWNLOAD_PATHS[locales.current] ?? segment.value
  if (canonical !== segment.value) {
    router.replace({ params: { ...route.params, dl: canonical } })
    return
  }

  await site.load() // el head usa documentTitle: sin carreras en el prerender

  const origin = window.location.origin
  useHead({
    title: site.documentTitle(t('downloads.title')),
    description: site.description || undefined,
    canonical: `${origin}/${locales.current}/${canonical}`,
    alternates: Object.fromEntries(
      Object.entries(DOWNLOAD_PATHS).map(([code, path]) => [code, `${origin}/${code}/${path}`]),
    ),
  })
}

watch([segment, () => locales.current], applyHead, { immediate: true })
</script>

<template>
  <main class="downloads">
    <IndexHeader :title="t('downloads.title')" :subtitle="t('downloads.intro')" />
    <DownloadsContent />
  </main>
</template>
