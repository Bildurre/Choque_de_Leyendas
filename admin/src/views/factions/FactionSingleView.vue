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
import InfoBlock from '@/components/InfoBlock.vue'
import DashBarPanel, { type BarRow } from '@/components/dashboard/DashBarPanel.vue'

// Single de facción al estilo del de héroe: la imagen subida tal cual,
// info-block con los datos (texto plano, sin chips), sección de lore con la
// cita y ESTADÍSTICAS de su contenido (cartas por tipo y coste, héroes por
// clase y superclase, mazos por modo) con las barras del dashboard.
const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const locales = useLocalesStore()
const { find } = useResource<Faction>(api, '/admin/factions')

const item = ref<Faction | null>(null)
const loading = ref(true)
const formOpen = ref(false)

// Estadísticas del endpoint stats (nombres YA localizados por la API).
interface NamedCount {
  id: number
  name: string
  count: number
}
interface FactionStats {
  cards: { total: number; by_type: NamedCount[]; cost_curve: { dice: number; count: number }[] }
  heroes: { total: number; by_class: NamedCount[]; by_superclass: NamedCount[] }
  decks: { total: number; by_game_mode: NamedCount[] }
}
const stats = ref<FactionStats | null>(null)

function tr(obj: Record<string, string> | null | undefined) {
  return (
    obj?.[locales.current] || obj?.[locales.defaultLocale] || Object.values(obj || {})[0] || '—'
  )
}
const slug = computed(() => route.params.slug as string)

interface BarPanel {
  key: string
  title: string
  rows: BarRow[]
  max: number
}

function panel(key: string, title: string, rows: BarRow[]): BarPanel {
  return { key, title, rows, max: Math.max(...rows.map((r) => Number(r.count)), 1) }
}

const statTiles = computed(() =>
  stats.value
    ? [
        { key: 'cards', value: stats.value.cards.total },
        { key: 'heroes', value: stats.value.heroes.total },
        { key: 'decks', value: stats.value.decks.total },
      ]
    : [],
)

// Una gráfica por métrica (solo las que tienen filas), barras del dashboard.
const statPanels = computed<BarPanel[]>(() => {
  if (!stats.value) return []
  const rows = (items: NamedCount[]): BarRow[] =>
    items.map((row) => ({ key: row.id, label: row.name, count: row.count }))
  return [
    panel('cards-by-type', t('factions.stats.cardsByType'), rows(stats.value.cards.by_type)),
    panel('heroes-by-class', t('factions.stats.heroesByClass'), rows(stats.value.heroes.by_class)),
    panel(
      'heroes-by-superclass',
      t('factions.stats.heroesBySuperclass'),
      rows(stats.value.heroes.by_superclass),
    ),
    panel('decks-by-mode', t('factions.stats.decksByMode'), rows(stats.value.decks.by_game_mode)),
  ].filter((p) => p.rows.length)
})

const costCurve = computed(() => stats.value?.cards.cost_curve ?? [])
const costCurveMax = computed(() => Math.max(...costCurve.value.map((c) => c.count), 1))

function pct(count: number, max: number): string {
  return `${(count / Math.max(max, 1)) * 100}%`
}

async function loadStats() {
  try {
    const { data } = await api.get(`/admin/factions/${slug.value}/stats`)
    stats.value = data.data
  } catch {
    stats.value = null
  }
}

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
  await Promise.all([load(), loadStats()])
}

onMounted(async () => {
  await locales.load()
  await Promise.all([load(), loadStats()])
})

// Los nombres de las estadísticas llegan localizados: se piden de nuevo al
// cambiar el idioma de contenido.
watch(
  () => locales.current,
  () => loadStats(),
)

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
  <div v-if="item" class="single faction-single">
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
        <!-- La imagen subida tal cual (o la inicial si aún no hay icono) -->
        <div class="faction-single__art">
          <img v-if="item.image" :src="item.image" alt="" />
          <span v-else class="faction-single__mono">{{ tr(item.name).charAt(0) }}</span>
        </div>
      </div>

      <div class="single__info">
        <h1>{{ tr(item.name) }}</h1>

        <!-- Sin chips: texto plano, el color con su muestra -->
        <InfoBlock :title="t('factions.sections.basic')">
          <dl class="info-list">
            <dt>{{ t('factions.fields.color') }}</dt>
            <dd>
              <span class="faction-swatch" :style="{ background: item.color || 'transparent' }" />{{
                item.color || '—'
              }}
            </dd>

            <dt>{{ t('factions.counts.heroes') }}</dt>
            <dd>{{ item.heroes_count ?? 0 }}</dd>

            <dt>{{ t('factions.counts.cards') }}</dt>
            <dd>{{ item.cards_count ?? 0 }}</dd>

            <dt>{{ t('factions.counts.decks') }}</dt>
            <dd>{{ item.faction_decks_count ?? 0 }}</dd>
          </dl>
        </InfoBlock>
      </div>
    </div>

    <template v-if="tr(item.lore_text) !== '—' || tr(item.epic_quote) !== '—'">
      <h2 class="single__section">{{ t('factions.sections.lore') }}</h2>
      <div class="rich-content" v-html="tr(item.lore_text)" />
      <blockquote
        v-if="tr(item.epic_quote) !== '—'"
        class="faction-single__quote rich-content"
        v-html="tr(item.epic_quote)"
      />
    </template>

    <!-- Estadísticas del contenido de la facción (barras del dashboard) -->
    <template v-if="stats">
      <h2 class="single__section">{{ t('factions.sections.stats') }}</h2>
      <div class="dashboard__tiles dashboard__tiles--sub">
        <article v-for="tile in statTiles" :key="tile.key" class="dash-tile">
          <span class="dash-tile__value">{{ tile.value }}</span>
          <span class="dash-tile__label">{{ t(`factions.counts.${tile.key}`) }}</span>
        </article>
      </div>
      <div class="dashboard__grid">
        <!-- Curva de coste de sus cartas: columnas por nº de dados -->
        <article v-if="stats.cards.total" class="dash-panel">
          <h3 class="dash-panel__title">{{ t('factions.stats.costCurve') }}</h3>
          <div class="dash-curve">
            <div v-for="col in costCurve" :key="col.dice" class="dash-curve__col">
              <span class="dash-curve__count">{{ col.count }}</span>
              <span class="dash-curve__track">
                <span class="dash-curve__fill" :style="{ height: pct(col.count, costCurveMax) }" />
              </span>
              <span class="dash-curve__label">{{ col.dice }}</span>
            </div>
          </div>
        </article>
        <DashBarPanel
          v-for="p in statPanels"
          :key="p.key"
          :title="p.title"
          :rows="p.rows"
          :max="p.max"
        />
      </div>
    </template>

    <FactionFormModal v-model="formOpen" mode="edit" :target-slug="slug" @saved="onSaved" />
  </div>
  <p v-else-if="!loading" class="single__empty">{{ t('common.empty') }}</p>
</template>
