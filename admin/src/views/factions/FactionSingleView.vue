<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { setActiveSlugMap } from '@/router'
import { ArrowLeft, SquarePen } from '@lucide/vue'
import { useResource } from '@edc-motor/admin-kit'
import { BaseButton } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { useLocalesStore } from '@/stores/locales'
import { usePageCrumb } from '@/composables/usePageCrumb'
import type { Faction } from '@juego/shared'
import FactionFormModal from '@/components/factions/FactionFormModal.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const locales = useLocalesStore()
const { find } = useResource<Faction>(api, '/admin/factions')

const item = ref<Faction | null>(null)
const loading = ref(true)
const formOpen = ref(false)

function tr(obj: Record<string, string> | null | undefined) {
  return (
    obj?.[locales.current] || obj?.[locales.defaultLocale] || Object.values(obj || {})[0] || '—'
  )
}
const slug = computed(() => route.params.slug as string)

async function load() {
  loading.value = true
  try {
    item.value = await find(slug.value)
    setActiveSlugMap(item.value?.slug ?? null) // slug localizado al cambiar idioma
  } catch {
    item.value = null
  } finally {
    loading.value = false
  }
}
async function onSaved() {
  await load()
}

onMounted(async () => {
  await locales.load()
  await load()
})

// El nombre del single como último tramo de la breadcrumb (se actualiza si
// cambia el locale de contenido) y fuera al salir de la vista.
const crumb = usePageCrumb()
watch(
  [item, () => locales.current],
  () => {
    if (item.value) crumb.set(tr(item.value.name))
  },
  { immediate: true },
)
onBeforeUnmount(() => {
  crumb.clear()
  setActiveSlugMap(null)
})
</script>

<!-- eslint-disable vue/no-v-html -- HTML del WYSIWYG propio (sanitización en servidor) -->
<template>
  <div v-if="item" class="single">
    <div class="single__bar">
      <BaseButton variant="text" @click="router.push({ name: 'factions' })">
        <template #icon><ArrowLeft :size="16" /></template>
        {{ t('factions.title') }}
      </BaseButton>
      <BaseButton variant="success" @click="formOpen = true">
        <template #icon><SquarePen :size="16" /></template>
        {{ t('common.actions.edit') }}
      </BaseButton>
    </div>

    <div class="single__layout">
      <div class="single__preview">
        <div
          class="faction-sheet"
          :class="{ 'faction-sheet--dark-text': item.text_is_dark }"
          :style="{ '--c': item.color || 'transparent' }"
        >
          <div class="faction-sheet__art">
            <img v-if="item.image" :src="item.image" alt="" />
            <span v-else class="faction-sheet__mono">{{ tr(item.name).charAt(0) }}</span>
          </div>
          <h3 class="faction-sheet__title">{{ tr(item.name) }}</h3>
          <blockquote
            v-if="tr(item.epic_quote) !== '—'"
            class="faction-sheet__quote rich-content"
            v-html="tr(item.epic_quote)"
          />
        </div>
      </div>
      <div class="single__info">
        <h1>{{ tr(item.name) }}</h1>
        <p class="single__meta">
          <span class="faction-swatch" :style="{ background: item.color || 'transparent' }" />{{
            item.color || '—'
          }}
        </p>
        <div class="rich-content" v-html="tr(item.lore_text)" />
      </div>
    </div>

    <!-- TODO: listar héroes y cartas de la facción cuando existan sus clusters. -->

    <FactionFormModal v-model="formOpen" mode="edit" :target-slug="slug" @saved="onSaved" />
  </div>
  <p v-else-if="!loading" class="single__empty">{{ t('common.empty') }}</p>
</template>
