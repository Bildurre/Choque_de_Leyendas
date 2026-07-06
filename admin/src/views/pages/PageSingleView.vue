<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, SquarePen } from '@lucide/vue'
import { BaseButton, useToast } from '@edc-motor/ui'
import { PageBlocks, type PageBlocksLabels } from '@edc-motor/admin-kit'
import { api } from '@/lib/api'
import { useLocalesStore } from '@/stores/locales'
import { useIconsStore } from '@/stores/icons'
import { usePageCrumb } from '@/composables/usePageCrumb'
import { useEditorLabels } from '@/lib/editorLabels'
import PageFormModal, { type PageRow } from '@/components/pages/PageFormModal.vue'

// Single de página: datos + gestor de bloques (PageBlocks del admin-kit).
const { t, te } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const locales = useLocalesStore()
const icons = useIconsStore()
const richLabels = useEditorLabels()

const iconList = computed(() =>
  icons.icons.filter((i) => i.url).map((i) => ({ name: i.name, url: i.url as string })),
)

const id = computed(() => Number(route.params.id))
const page = ref<PageRow | null>(null)
const pages = ref<PageRow[]>([])
const formOpen = ref(false)

// El gancho de localización del editor de bloques: si el juego tiene la
// clave i18n, se usa; si no, la etiqueta del esquema (castellano).
function translate(key: string, fallback: string): string {
  return te(key) ? t(key) : fallback
}

const blockLabels = computed<Partial<PageBlocksLabels>>(() => ({
  add: t('pages.blocks.add'),
  edit: t('pages.blocks.edit'),
  delete: t('common.actions.delete'),
  save: t('common.save'),
  cancel: t('common.cancel'),
  empty: t('pages.blocks.empty'),
  printable: t('pages.blocks.printable'),
  indexable: t('pages.blocks.indexable'),
  common: t('pages.blocks.common'),
  confirmDelete: t('pages.blocks.confirmDelete'),
  error: t('common.errors.action'),
  panelTitle: t('pages.blocks.panelTitle'),
  panelEmpty: t('pages.blocks.panelEmpty'),
  panelContent: t('pages.blocks.panelContent'),
}))

async function load() {
  try {
    const [single, all] = await Promise.all([
      api.get(`/admin/pages/${id.value}`),
      api.get('/admin/pages'),
    ])
    page.value = single.data.data
    pages.value = all.data.data
  } catch {
    toast.danger(t('common.errors.load'))
  }
}

onMounted(async () => {
  await locales.load()
  await icons.load()
  await load()
})

const crumb = usePageCrumb()
watch(
  [page, () => locales.current],
  () => {
    if (page.value) crumb.set(page.value.title[locales.current] ?? page.value.title.es ?? '')
  },
  { immediate: true },
)
onBeforeUnmount(crumb.clear)
</script>

<template>
  <div v-if="page" class="single">
    <div class="single__bar">
      <BaseButton variant="text" @click="router.push({ name: 'pages' })">
        <template #icon><ArrowLeft :size="16" /></template>
        {{ t('pages.title') }}
      </BaseButton>
      <BaseButton variant="success" @click="formOpen = true">
        <template #icon><SquarePen :size="16" /></template>
        {{ t('common.actions.edit') }}
      </BaseButton>
    </div>

    <h1 class="single__title">{{ page.title[locales.current] ?? page.title.es }}</h1>

    <PageBlocks
      :api="api"
      :page-id="page.id"
      :locales="locales.locales"
      :icons="iconList"
      :rich-labels="richLabels"
      :labels="blockLabels"
      :translate="translate"
    />

    <PageFormModal v-model="formOpen" :page="page" :pages="pages" @saved="load" />
  </div>
</template>
