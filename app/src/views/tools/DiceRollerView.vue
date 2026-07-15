<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ConfirmDialog, useHead } from '@edc-motor/ui'
import DiceRoller from '@/components/tools/DiceRoller.vue'
import { DICE_ROLLER_PATHS, TOOLS_PATHS } from '@/router/tools'
import { useLocalesStore } from '@/stores/locales'
import { useSiteStore } from '@/stores/site'

// Vista propia del lanzador de dados (herramienta pública, pensada para
// MÓVIL): /es/herramientas/lanzador-de-dados · /en/tools/dice-roller. El
// lanzador en sí es el componente reutilizable DiceRoller (también embebido
// en el contador de vidas, compartiendo estado por localStorage); aquí solo
// viven la canónica del locale (DC-12, como el contador) y el head.

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const locales = useLocalesStore()
const site = useSiteStore()

const segTools = computed(() => String(route.params.tools ?? ''))
const segTool = computed(() => String(route.params.tool ?? ''))

function canonicalize(): boolean {
  const tools = TOOLS_PATHS[locales.current] ?? segTools.value
  const tool = DICE_ROLLER_PATHS[locales.current] ?? segTool.value
  if (tools === segTools.value && tool === segTool.value) return false
  router.replace({ params: { ...route.params, tools, tool } })
  return true
}

async function applyHead() {
  await site.load() // el head usa documentTitle: sin carreras en el prerender
  const origin = window.location.origin
  useHead({
    title: site.documentTitle(t('tools.diceRoller.title')),
    description: site.description || undefined,
    canonical: `${origin}/${locales.current}/${TOOLS_PATHS[locales.current]}/${DICE_ROLLER_PATHS[locales.current]}`,
    alternates: Object.fromEntries(
      Object.keys(TOOLS_PATHS).map((code) => [
        code,
        `${origin}/${code}/${TOOLS_PATHS[code]}/${DICE_ROLLER_PATHS[code]}`,
      ]),
    ),
  })
}

watch(
  [segTools, segTool, () => locales.current],
  () => {
    if (!canonicalize()) void applyHead()
  },
  { immediate: true },
)
</script>

<template>
  <main class="dice-roller-view">
    <h1 class="dice-roller-view__title">{{ t('tools.diceRoller.title') }}</h1>
    <p class="dice-roller-view__intro">{{ t('tools.diceRoller.intro') }}</p>

    <DiceRoller />

    <!-- Diálogo global de confirmación del motor (limpiar el lanzador) -->
    <ConfirmDialog />
  </main>
</template>
