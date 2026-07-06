<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { PageBackground, useHead } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { blockRegistry } from '@/blocks/registry'
import { templateFor } from '@/templates/registry'
import { useLocalesStore } from '@/stores/locales'
import { useSiteStore } from '@/stores/site'

// Página pública del CRM (doc 03): pide el payload por slug (resuelto en
// cualquier locale), redirige a la URL canónica del idioma activo (DC-12),
// envuelve los bloques en la plantilla de la página (templateRegistry) y
// monta el componente de cada bloque desde el blockRegistry.
interface PagePayload {
  id: number
  title: string
  template: string | null
  background_image: string | null
  meta: { title: string; description: string }
  slugs: Record<string, string>
  blocks: {
    id: number
    component: string
    settings: Record<string, unknown>
    data: Record<string, unknown>
  }[]
}

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const locales = useLocalesStore()
const site = useSiteStore()

const page = ref<PagePayload | null>(null)
const failed = ref(false)

const slug = computed(() => String(route.params.slug ?? ''))

async function load() {
  failed.value = false
  try {
    // La config del sitio entra en el head (documentTitle): esperarla evita
    // títulos sin el sufijo del sitio en el prerender.
    await site.load()
    const { data } = await api.get(`/pages/${slug.value}`)
    page.value = data.data

    // Canónica: si el slug de la URL no es el del idioma activo, se sustituye.
    const canonical = page.value?.slugs?.[locales.current]
    if (canonical && canonical !== slug.value) {
      router.replace({ name: 'page', params: { ...route.params, slug: canonical } })
      return
    }

    // SEO (doc 10): título/description de la página + canonical y hreflang
    // desde sus slugs por locale.
    const origin = window.location.origin
    useHead({
      title: site.documentTitle(page.value?.meta.title),
      description: page.value?.meta.description || site.description || undefined,
      canonical: `${origin}/${locales.current}/${slug.value}`,
      alternates: Object.fromEntries(
        Object.entries(page.value?.slugs ?? {}).map(([code, s]) => [
          code,
          `${origin}/${code}/${s}`,
        ]),
      ),
    })
  } catch {
    failed.value = true
  }
}

watch([slug, () => locales.current], load, { immediate: true })
</script>

<template>
  <main v-if="failed" class="page-view">
    <p class="page-view__missing">{{ t('page.notFound') }}</p>
  </main>
  <template v-else-if="page">
    <PageBackground :image="page.background_image" />
    <component :is="templateFor(page.template)">
      <component
        :is="blockRegistry[block.component]"
        v-for="block in page.blocks.filter((b) => blockRegistry[b.component])"
        :id="`block-${block.id}`"
        :key="block.id"
        :settings="block.settings"
        :data="block.data"
      />
    </component>
  </template>
</template>
