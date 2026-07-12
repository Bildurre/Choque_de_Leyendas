<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useLocalesStore } from '@/stores/locales'
import DashBarPanel, { type BarRow } from '@/components/dashboard/DashBarPanel.vue'

// Panel con las estadísticas del juego (portado del dashboard del viejo):
// tarjetas de totales + gráficas de barras en CSS puro. Los nombres llegan
// ya localizados de la API (?locale lo inyecta el cliente).

interface Ping {
  name: string
  version: string
  locales: string[]
}

interface EntityTotal {
  total: number
  published?: number
}

interface FactionRow {
  id: number
  name: string
  color: string
  is_published: boolean
  heroes: number
  cards: number
  decks: number
}

interface NamedCount {
  id: number
  name: string
  count: number
  published?: number
}

interface AttributeStat {
  avg: number
  min: number
  max: number
}

interface DashboardStats {
  totals: Record<string, EntityTotal>
  factions: FactionRow[]
  cards: {
    by_type: NamedCount[]
    cost_curve: { dice: number; count: number }[]
    cost_colors: Record<'R' | 'G' | 'B', number>
    avg_cost: number
    attack_types: { physical: number; magical: number }
    equipment: { weapon: number; armor: number }
    area: number
    unique: number
  }
  heroes: {
    by_superclass: NamedCount[]
    by_race: NamedCount[]
    gender: { male: number; female: number }
    attributes: Record<string, AttributeStat>
  }
  decks: {
    by_game_mode: NamedCount[]
    avg_cards: number
    avg_heroes: number
  }
}

interface BarPanel {
  key: string
  title: string
  rows: BarRow[]
  max: number
}

const { t } = useI18n()
const auth = useAuthStore()
const locales = useLocalesStore()
const ping = ref<Ping | null>(null)
const stats = ref<DashboardStats | null>(null)

// Colores fijos de los dados (identidad del juego, no de la paleta del admin).
const DICE_COLORS: Record<string, string> = { R: '#d64545', G: '#3e9c5c', B: '#3d6fd8' }

async function loadStats() {
  try {
    const { data } = await api.get('/admin/dashboard/stats')
    stats.value = data.data
  } catch {
    stats.value = null
  }
}

function panel(key: string, title: string, rows: BarRow[]): BarPanel {
  return { key, title, rows, max: Math.max(...rows.map((r) => Number(r.count)), 1) }
}

function pct(count: number, max: number): string {
  return `${(count / Math.max(max, 1)) * 100}%`
}

// Orden de tarjetas de totales (la API manda un mapa; aquí se fija el orden).
const TOTAL_KEYS = [
  'factions',
  'heroes',
  'cards',
  'faction_decks',
  'counters',
  'hero_abilities',
  'game_modes',
]
const totals = computed(() =>
  stats.value
    ? TOTAL_KEYS.filter((key) => stats.value!.totals[key]).map((key) => ({
        key,
        ...stats.value!.totals[key],
      }))
    : [],
)

// Facciones: una gráfica por métrica, barras con el color de cada facción.
const factionPanels = computed<BarPanel[]>(() => {
  if (!stats.value) return []
  const rows = (metric: 'cards' | 'heroes' | 'decks'): BarRow[] =>
    stats.value!.factions.map((f) => ({
      key: f.id,
      label: f.name,
      count: f[metric],
      color: f.color,
    }))
  return [
    panel('faction-cards', t('dashboard.charts.cardsByFaction'), rows('cards')),
    panel('faction-heroes', t('dashboard.charts.heroesByFaction'), rows('heroes')),
    panel('faction-decks', t('dashboard.charts.decksByFaction'), rows('decks')),
  ]
})

const cardPanels = computed<BarPanel[]>(() => {
  if (!stats.value) return []
  const cards = stats.value.cards
  return [
    panel(
      'card-types',
      t('dashboard.charts.cardsByType'),
      cards.by_type.map((row) => ({ key: row.id, label: row.name, count: row.count })),
    ),
    panel(
      'cost-colors',
      t('dashboard.charts.costColors'),
      (['R', 'G', 'B'] as const).map((color) => ({
        key: color,
        label: t(`dashboard.labels.dice${color}`),
        count: cards.cost_colors[color],
        color: DICE_COLORS[color],
      })),
    ),
    panel('attack-types', t('dashboard.charts.attackTypes'), [
      {
        key: 'physical',
        label: t('dashboard.labels.physical'),
        count: cards.attack_types.physical,
      },
      { key: 'magical', label: t('dashboard.labels.magical'), count: cards.attack_types.magical },
    ]),
    panel('equipment', t('dashboard.charts.equipment'), [
      { key: 'weapon', label: t('dashboard.labels.weapons'), count: cards.equipment.weapon },
      { key: 'armor', label: t('dashboard.labels.armors'), count: cards.equipment.armor },
    ]),
  ]
})

const costCurve = computed(() => stats.value?.cards.cost_curve ?? [])
const costCurveMax = computed(() => Math.max(...costCurve.value.map((c) => c.count), 1))

const cardTiles = computed(() =>
  stats.value
    ? [
        { key: 'avgCost', value: stats.value.cards.avg_cost },
        { key: 'area', value: stats.value.cards.area },
        { key: 'unique', value: stats.value.cards.unique },
      ]
    : [],
)

const ATTRIBUTE_KEYS = ['agility', 'mental', 'will', 'strength', 'armor']

const heroPanels = computed<BarPanel[]>(() => {
  if (!stats.value) return []
  const heroes = stats.value.heroes

  // Atributos como barras de la media, con el rango mín–máx de sufijo.
  const attrMax = Math.max(...ATTRIBUTE_KEYS.map((key) => heroes.attributes[key]?.max ?? 0), 1)
  const attrRows: BarRow[] = ATTRIBUTE_KEYS.map((key) => ({
    key,
    label: t(`dashboard.labels.${key}`),
    count: heroes.attributes[key]?.avg ?? 0,
    suffix: `(${heroes.attributes[key]?.min ?? 0}–${heroes.attributes[key]?.max ?? 0})`,
  }))

  return [
    panel(
      'superclasses',
      t('dashboard.charts.bySuperclass'),
      heroes.by_superclass.map((row) => ({ key: row.id, label: row.name, count: row.count })),
    ),
    panel(
      'races',
      t('dashboard.charts.byRace'),
      heroes.by_race.map((row) => ({ key: row.id, label: row.name, count: row.count })),
    ),
    panel('gender', t('dashboard.charts.gender'), [
      { key: 'male', label: t('dashboard.labels.male'), count: heroes.gender.male },
      { key: 'female', label: t('dashboard.labels.female'), count: heroes.gender.female },
    ]),
    { key: 'attributes', title: t('dashboard.charts.attributes'), rows: attrRows, max: attrMax },
  ]
})

const deckPanels = computed<BarPanel[]>(() => {
  if (!stats.value) return []
  return [
    panel(
      'game-modes',
      t('dashboard.charts.decksByMode'),
      stats.value.decks.by_game_mode.map((row) => ({
        key: row.id,
        label: row.name,
        count: row.count,
      })),
    ),
  ]
})

const deckTiles = computed(() =>
  stats.value
    ? [
        { key: 'avgCards', value: stats.value.decks.avg_cards },
        { key: 'avgHeroes', value: stats.value.decks.avg_heroes },
      ]
    : [],
)

// Al cambiar el idioma del admin los nombres localizados se piden de nuevo.
watch(() => locales.current, loadStats)

onMounted(async () => {
  loadStats()
  try {
    const { data } = await api.get('/motor/ping')
    ping.value = data
  } catch {
    /* endpoint opcional */
  }
})
</script>

<template>
  <div class="dashboard">
    <template v-if="stats">
      <!-- Totales por entidad -->
      <section class="dashboard__section">
        <h2 class="dashboard__section-title">{{ t('dashboard.sections.totals') }}</h2>
        <div class="dashboard__tiles">
          <article v-for="tile in totals" :key="tile.key" class="dash-tile">
            <span class="dash-tile__value">{{ tile.total }}</span>
            <span class="dash-tile__label">{{ t(`dashboard.totals.${tile.key}`) }}</span>
            <span v-if="tile.published !== undefined" class="dash-tile__sub">
              {{ t('dashboard.publishedCount', { n: tile.published }) }}
            </span>
          </article>
        </div>
      </section>

      <!-- Facciones -->
      <section class="dashboard__section">
        <h2 class="dashboard__section-title">{{ t('dashboard.sections.factions') }}</h2>
        <div class="dashboard__grid">
          <DashBarPanel
            v-for="p in factionPanels"
            :key="p.key"
            :title="p.title"
            :rows="p.rows"
            :max="p.max"
          />
        </div>
      </section>

      <!-- Cartas -->
      <section class="dashboard__section">
        <h2 class="dashboard__section-title">{{ t('dashboard.sections.cards') }}</h2>
        <div class="dashboard__tiles dashboard__tiles--sub">
          <article v-for="tile in cardTiles" :key="tile.key" class="dash-tile">
            <span class="dash-tile__value">{{ tile.value }}</span>
            <span class="dash-tile__label">{{ t(`dashboard.labels.${tile.key}`) }}</span>
          </article>
        </div>
        <div class="dashboard__grid">
          <!-- Curva de coste: columnas por nº de dados -->
          <article class="dash-panel">
            <h3 class="dash-panel__title">{{ t('dashboard.charts.costCurve') }}</h3>
            <div class="dash-curve">
              <div v-for="col in costCurve" :key="col.dice" class="dash-curve__col">
                <span class="dash-curve__count">{{ col.count }}</span>
                <span class="dash-curve__track">
                  <span
                    class="dash-curve__fill"
                    :style="{ height: pct(col.count, costCurveMax) }"
                  ></span>
                </span>
                <span class="dash-curve__label">{{ col.dice }}</span>
              </div>
            </div>
          </article>
          <DashBarPanel
            v-for="p in cardPanels"
            :key="p.key"
            :title="p.title"
            :rows="p.rows"
            :max="p.max"
          />
        </div>
      </section>

      <!-- Héroes -->
      <section class="dashboard__section">
        <h2 class="dashboard__section-title">{{ t('dashboard.sections.heroes') }}</h2>
        <div class="dashboard__grid">
          <DashBarPanel
            v-for="p in heroPanels"
            :key="p.key"
            :title="p.title"
            :rows="p.rows"
            :max="p.max"
          />
        </div>
      </section>

      <!-- Mazos -->
      <section class="dashboard__section">
        <h2 class="dashboard__section-title">{{ t('dashboard.sections.decks') }}</h2>
        <div class="dashboard__tiles dashboard__tiles--sub">
          <article v-for="tile in deckTiles" :key="tile.key" class="dash-tile">
            <span class="dash-tile__value">{{ tile.value }}</span>
            <span class="dash-tile__label">{{ t(`dashboard.labels.${tile.key}`) }}</span>
          </article>
        </div>
        <div class="dashboard__grid">
          <DashBarPanel
            v-for="p in deckPanels"
            :key="p.key"
            :title="p.title"
            :rows="p.rows"
            :max="p.max"
          />
        </div>
      </section>
    </template>

    <!-- Pie discreto: sesión y ping del motor -->
    <footer class="dashboard__foot">
      <p v-if="auth.user">
        {{
          t('dashboard.connectedAs', { name: auth.user.name, roles: auth.user.roles.join(', ') })
        }}
      </p>
      <p v-if="ping">
        {{
          t('dashboard.motor', {
            name: ping.name,
            version: ping.version,
            locales: ping.locales.join(', '),
          })
        }}
      </p>
    </footer>
  </div>
</template>
