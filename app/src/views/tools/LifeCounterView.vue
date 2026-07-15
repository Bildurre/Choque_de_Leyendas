<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ChevronDown, Play, RotateCcw, Square } from '@lucide/vue'
import {
  BaseButton,
  BaseSelect,
  ConfirmDialog,
  NumericInput,
  useConfirm,
  useHead,
} from '@edc-motor/ui'
import { api } from '@/lib/api'
import DiceRoller from '@/components/tools/DiceRoller.vue'
import { LIFE_COUNTER_PATHS, TOOLS_PATHS } from '@/router/tools'
import { useAuthStore } from '@/stores/auth'
import { useLocalesStore } from '@/stores/locales'
import { useSiteStore } from '@/stores/site'

// Contador de vidas para las partidas físicas (herramienta pública, pensada
// para MÓVIL). Dos fases: preparación (nº de héroes por equipo, facciones y
// héroes de cada equipo sobre los endpoints públicos existentes) y partida
// (vidas por héroe con −/+ grandes; vida inicial = la salud derivada que
// expone la ficha pública del héroe). La partida en curso vive SIEMPRE en
// localStorage (recargar restaura en modo partida) y, si hay sesión, también
// en el servidor (histórico "Partidas anteriores": retomar la activa o ver
// las terminadas). Con la partida en marcha se pide el wake lock de pantalla
// (feature-detect silencioso: sin soporte, todo funciona igual).

interface FactionOption {
  id: number
  name: string
  color: string | null
}

/** Ítem del índice público de héroes (forma del catálogo del motor). */
interface HeroOption {
  id: number
  name: string
  slug: string | null
  preview: string | null
}

interface MatchHero extends HeroOption {
  health: number
  lives: number
}

interface MatchTeam {
  factions: FactionOption[]
  heroes: MatchHero[]
}

interface MatchState {
  startedAt: string
  teams: MatchTeam[] // [mi equipo, equipo rival]
}

interface ServerMatch {
  id: number
  state: MatchState
  status: 'active' | 'finished'
  created_at: string
  updated_at: string
}

interface TeamSetup {
  factionIds: number[]
  slots: string[] // id del héroe elegido por hueco ('' = vacío)
}

// El habitual del juego: la configuración de mazo estándar exige 5 héroes.
const DEFAULT_HEROES = 5
const MAX_HEROES = 6
const STORAGE_KEY = 'cdl_life_counter_match'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const auth = useAuthStore()
const locales = useLocalesStore()
const site = useSiteStore()
const { confirm } = useConfirm()

const phase = ref<'setup' | 'match'>('setup')

// --- Canónica + head (misma mecánica que las Descargas, DC-12) ---

const segTools = computed(() => String(route.params.tools ?? ''))
const segTool = computed(() => String(route.params.tool ?? ''))

function canonicalize(): boolean {
  const tools = TOOLS_PATHS[locales.current] ?? segTools.value
  const tool = LIFE_COUNTER_PATHS[locales.current] ?? segTool.value
  if (tools === segTools.value && tool === segTool.value) return false
  router.replace({ params: { ...route.params, tools, tool } })
  return true
}

async function applyHead() {
  await site.load() // el head usa documentTitle: sin carreras en el prerender
  const origin = window.location.origin
  useHead({
    title: site.documentTitle(t('tools.lifeCounter.title')),
    description: site.description || undefined,
    canonical: `${origin}/${locales.current}/${TOOLS_PATHS[locales.current]}/${LIFE_COUNTER_PATHS[locales.current]}`,
    alternates: Object.fromEntries(
      Object.keys(TOOLS_PATHS).map((code) => [
        code,
        `${origin}/${code}/${TOOLS_PATHS[code]}/${LIFE_COUNTER_PATHS[code]}`,
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

// --- Preparación: nº de héroes, facciones y héroes por equipo ---

const heroCount = ref(DEFAULT_HEROES)
const setupTeams = reactive<TeamSetup[]>([
  { factionIds: [], slots: Array(DEFAULT_HEROES).fill('') },
  { factionIds: [], slots: Array(DEFAULT_HEROES).fill('') },
])

// Los huecos siguen al input (se conservan los ya elegidos que quepan).
watch(heroCount, (count) => {
  for (const team of setupTeams) {
    team.slots = Array.from({ length: count }, (_, i) => team.slots[i] ?? '')
  }
})

const factions = ref<FactionOption[]>([])

async function loadFactions() {
  try {
    // Facciones publicadas con color, ya localizadas (filtros del índice).
    const { data } = await api.get('/heroes/filters')
    factions.value = Array.isArray(data.factions) ? data.factions : []
  } catch {
    factions.value = []
  }
}

function toggleFaction(team: TeamSetup, id: number) {
  team.factionIds = team.factionIds.includes(id)
    ? team.factionIds.filter((existing) => existing !== id)
    : [...team.factionIds, id]
}

// Héroes publicados por facción (clave 0 = todas), cacheados por locale.
// El índice público pagina: se recorren las páginas (tope prudente).
const heroesByFaction = reactive(new Map<number, HeroOption[]>())
const loadingPools = reactive(new Set<number>())

async function ensurePool(key: number) {
  if (heroesByFaction.has(key) || loadingPools.has(key)) return
  loadingPools.add(key)
  try {
    const heroes: HeroOption[] = []
    let page = 1
    let last = 1
    do {
      const { data } = await api.get('/heroes', {
        params: { page, per_page: 48, sort: 'name', faction_id: key || undefined },
      })
      heroes.push(...(data.data as HeroOption[]))
      last = Number(data.meta?.last_page ?? 1)
      page += 1
    } while (page <= last && page <= 10)
    heroesByFaction.set(key, heroes)
  } catch {
    // sin red: se reintentará cuando vuelva a hacer falta
  } finally {
    loadingPools.delete(key)
  }
}

/** Claves de pool que necesita un equipo según sus facciones elegidas. */
function poolKeys(team: TeamSetup): number[] {
  return team.factionIds.length ? team.factionIds : [0]
}

/** Héroes elegibles del equipo (sin facción elegida: todos), por nombre. */
function poolFor(team: TeamSetup): HeroOption[] {
  const seen = new Map<number, HeroOption>()
  for (const key of poolKeys(team)) {
    for (const hero of heroesByFaction.get(key) ?? []) seen.set(hero.id, hero)
  }
  return [...seen.values()].sort((a, b) => a.name.localeCompare(b.name, locales.current))
}

function poolLoading(team: TeamSetup): boolean {
  return poolKeys(team).some((key) => loadingPools.has(key))
}

// Al cambiar las facciones: carga los pools que falten y desecha los héroes
// elegidos que ya no sean elegibles.
watch(
  () => setupTeams.map((team) => [...team.factionIds]),
  async () => {
    await Promise.all(setupTeams.flatMap((team) => poolKeys(team).map(ensurePool)))
    for (const team of setupTeams) {
      const eligible = new Set(poolFor(team).map((hero) => String(hero.id)))
      team.slots = team.slots.map((value) => (value && eligible.has(value) ? value : ''))
    }
  },
  { immediate: true, deep: true },
)

// Los nombres llegan localizados del servidor: al cambiar de idioma se
// recargan facciones y pools (la partida en marcha es una foto y no cambia).
watch(
  () => locales.current,
  async () => {
    await loadFactions()
    heroesByFaction.clear()
    await Promise.all(setupTeams.flatMap((team) => poolKeys(team).map(ensurePool)))
  },
  { immediate: true },
)

/** Opciones del hueco: el pool sin los héroes ya elegidos en OTROS huecos. */
function slotOptions(team: TeamSetup, index: number) {
  const takenElsewhere = new Set(team.slots.filter((value, i) => value && i !== index))
  return poolFor(team)
    .filter((hero) => !takenElsewhere.has(String(hero.id)))
    .map((hero) => ({ value: String(hero.id), label: hero.name }))
}

// Para comenzar, TODOS los huecos con héroe (para jugar con menos héroes
// se baja el input de arriba, que vale para ambos equipos).
const canStart = computed(() => setupTeams.every((team) => team.slots.every((value) => value)))

function resetSetup() {
  heroCount.value = DEFAULT_HEROES
  for (const team of setupTeams) {
    team.factionIds = []
    team.slots = Array(DEFAULT_HEROES).fill('')
  }
}

// --- Partida ---

const match = ref<MatchState | null>(null)
const serverId = ref<number | null>(null)
const starting = ref(false)
const startError = ref(false)

async function startMatch() {
  if (!canStart.value || starting.value) return
  starting.value = true
  startError.value = false
  try {
    const chosen = setupTeams.map((team) => {
      const pool = poolFor(team)
      return team.slots.map((value) => pool.find((hero) => String(hero.id) === value)!)
    })

    // Vida inicial = salud del héroe: la expone la ficha pública
    // (/api/heroes/{slug}, atributo derivado `health`).
    const healthBySlug = new Map<string, number>()
    const slugs = [...new Set(chosen.flat().flatMap((hero) => (hero.slug ? [hero.slug] : [])))]
    await Promise.all(
      slugs.map(async (slug) => {
        const { data } = await api.get(`/heroes/${encodeURIComponent(slug)}`)
        healthBySlug.set(slug, Number(data.data?.health ?? 1))
      }),
    )

    match.value = {
      startedAt: new Date().toISOString(),
      teams: chosen.map((heroes, index) => ({
        factions: setupTeams[index].factionIds
          .map((id) => factions.value.find((faction) => faction.id === id))
          .filter((faction): faction is FactionOption => !!faction),
        heroes: heroes.map((hero) => {
          const health = Math.max(1, healthBySlug.get(hero.slug ?? '') ?? 1)
          return { ...hero, health, lives: health }
        }),
      })),
    }
    serverId.value = null
    phase.value = 'match'
    persist()
    void acquireWakeLock()

    // Con sesión, la partida también se guarda en el servidor (histórico);
    // si falla, sigue funcionando solo con localStorage.
    if (auth.isAuthenticated) {
      try {
        const { data } = await api.post('/life-counter/matches', { state: match.value })
        serverId.value = Number(data.data.id)
        persist()
      } catch {
        serverId.value = null
      }
    }
  } catch {
    startError.value = true
  } finally {
    starting.value = false
  }
}

function setLives(hero: MatchHero, value: number) {
  hero.lives = Math.max(0, value)
}

async function finishMatch() {
  const confirmed = await confirm({
    title: t('tools.lifeCounter.endConfirmTitle'),
    message: t('tools.lifeCounter.endConfirmMessage'),
    confirmLabel: t('tools.lifeCounter.endConfirm'),
    cancelLabel: t('tools.lifeCounter.cancel'),
    variant: 'danger',
  })
  if (!confirmed) return

  if (syncTimer) {
    clearTimeout(syncTimer)
    syncTimer = null
  }
  if (serverId.value && auth.isAuthenticated) {
    try {
      await api.post(`/life-counter/matches/${serverId.value}/finish`, { state: match.value })
    } catch {
      // sin red: la partida queda como quedó en el servidor
    }
  }
  match.value = null
  serverId.value = null
  phase.value = 'setup'
  persist()
  releaseWakeLock()
  if (auth.isAuthenticated) void loadHistory()
}

// --- Persistencia local + sincronización debounceada con el servidor ---

function persist() {
  if (phase.value === 'match' && match.value) {
    localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({ state: match.value, serverId: serverId.value }),
    )
  } else {
    localStorage.removeItem(STORAGE_KEY)
  }
}

let syncTimer: ReturnType<typeof setTimeout> | null = null

function scheduleSync() {
  if (!serverId.value || !auth.isAuthenticated) return
  if (syncTimer) clearTimeout(syncTimer)
  // Debounce ~2 s: los −/+ rápidos viajan en un único PUT.
  syncTimer = setTimeout(() => {
    syncTimer = null
    void pushState()
  }, 2000)
}

async function pushState() {
  if (!serverId.value || !match.value) return
  try {
    await api.put(`/life-counter/matches/${serverId.value}`, { state: match.value })
  } catch {
    // sin red: localStorage sigue siendo la copia buena
  }
}

// Cada cambio de vidas persiste en local y (con sesión) programa el PUT.
watch(
  match,
  () => {
    if (phase.value !== 'match') return
    persist()
    scheduleSync()
  },
  { deep: true },
)

function restore(): boolean {
  const saved = localStorage.getItem(STORAGE_KEY)
  if (!saved) return false
  try {
    const parsed = JSON.parse(saved) as { state?: MatchState; serverId?: number | null }
    if (!Array.isArray(parsed.state?.teams) || parsed.state.teams.length !== 2) return false
    match.value = parsed.state
    serverId.value = parsed.serverId ?? null
    phase.value = 'match'
    return true
  } catch {
    localStorage.removeItem(STORAGE_KEY)
    return false
  }
}

// --- Wake lock: la pantalla no se apaga con la partida en marcha ---

// Estado observable (atributo data-* del board) para poder verificarlo sin
// consola: unsupported | idle | active | denied.
const wakeLockStatus = ref<'unsupported' | 'idle' | 'active' | 'denied'>(
  'wakeLock' in navigator ? 'idle' : 'unsupported',
)
let sentinel: WakeLockSentinel | null = null

async function acquireWakeLock() {
  if (!('wakeLock' in navigator) || phase.value !== 'match') return
  if (sentinel && !sentinel.released) return
  try {
    sentinel = await navigator.wakeLock.request('screen')
    wakeLockStatus.value = 'active'
    sentinel.addEventListener('release', () => {
      if (wakeLockStatus.value === 'active') wakeLockStatus.value = 'idle'
    })
  } catch {
    // denegado o no disponible: la herramienta funciona igual
    sentinel = null
    wakeLockStatus.value = 'denied'
  }
}

function releaseWakeLock() {
  sentinel?.release().catch(() => {})
  sentinel = null
  if (wakeLockStatus.value === 'active') wakeLockStatus.value = 'idle'
}

// Al volver a ser visible (móvil bloqueado, cambio de pestaña…), el wake
// lock se pierde: se re-adquiere si la partida sigue en marcha.
function onVisibilityChange() {
  if (document.visibilityState === 'visible') void acquireWakeLock()
}

// --- Lanzador de dados embebido (partida) ---

// Plegable bajo los equipos, cerrado por defecto: disponible durante la
// partida sin estorbar al conteo. Es el MISMO lanzador que la vista propia
// (el componente comparte su estado por localStorage).
const diceOpen = ref(false)

// --- Histórico (solo usuarios registrados) ---

const historyOpen = ref(false)
const historyLoading = ref(false)
const historyMatches = ref<ServerMatch[]>([])
const viewingId = ref<number | null>(null)

async function loadHistory() {
  historyLoading.value = true
  try {
    const { data } = await api.get('/life-counter/matches')
    historyMatches.value = Array.isArray(data.data) ? data.data : []
  } catch {
    historyMatches.value = []
  } finally {
    historyLoading.value = false
  }
}

function resumeMatch(saved: ServerMatch) {
  match.value = saved.state
  serverId.value = saved.id
  phase.value = 'match'
  persist()
  void acquireWakeLock()
}

function matchDate(saved: ServerMatch): string {
  return new Date(saved.updated_at).toLocaleString(locales.current, {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
}

function matchSummary(saved: ServerMatch): string {
  return saved.state.teams
    .map((team) => team.heroes.map((hero) => hero.name).join(', '))
    .join(' — ')
}

watch(
  [() => auth.isAuthenticated, phase],
  ([logged, current]) => {
    if (logged && current === 'setup') void loadHistory()
  },
  { immediate: true },
)

// --- Ciclo de vida ---

onMounted(() => {
  document.addEventListener('visibilitychange', onVisibilityChange)
  // Con partida activa guardada, directo al modo partida (recarga incluida).
  if (restore()) void acquireWakeLock()
})

onUnmounted(() => {
  document.removeEventListener('visibilitychange', onVisibilityChange)
  if (syncTimer) {
    clearTimeout(syncTimer)
    syncTimer = null
    void pushState() // el último cambio no se queda sin subir
  }
  releaseWakeLock()
})
</script>

<template>
  <main class="life-counter">
    <h1 class="life-counter__title">{{ t('tools.lifeCounter.title') }}</h1>

    <!-- Preparación: nº de héroes + dos equipos (facciones y héroes) -->
    <template v-if="phase === 'setup'">
      <p class="life-counter__intro">{{ t('tools.lifeCounter.intro') }}</p>

      <div class="life-counter__config">
        <NumericInput
          v-model="heroCount"
          class="life-counter__count"
          :label="t('tools.lifeCounter.heroesPerTeam')"
          :min="1"
          :max="MAX_HEROES"
        />
        <BaseButton variant="secondary" @click="resetSetup">
          <template #icon><RotateCcw :size="16" /></template>
          {{ t('tools.lifeCounter.reset') }}
        </BaseButton>
      </div>

      <div class="life-counter__teams">
        <section
          v-for="(team, teamIndex) in setupTeams"
          :key="teamIndex"
          class="life-counter__team"
        >
          <h2 class="life-counter__team-title">
            {{ t(teamIndex === 0 ? 'tools.lifeCounter.myTeam' : 'tools.lifeCounter.rivalTeam') }}
          </h2>

          <!-- Multiselect táctil de facciones: chips con el color de cada una -->
          <div
            class="life-counter__factions"
            role="group"
            :aria-label="
              t(
                teamIndex === 0
                  ? 'tools.lifeCounter.myFactions'
                  : 'tools.lifeCounter.rivalFactions',
              )
            "
          >
            <span class="life-counter__factions-label">
              {{
                t(
                  teamIndex === 0
                    ? 'tools.lifeCounter.myFactions'
                    : 'tools.lifeCounter.rivalFactions',
                )
              }}
            </span>
            <div class="life-counter__chips">
              <button
                v-for="faction in factions"
                :key="faction.id"
                type="button"
                class="life-counter__chip"
                :class="{ 'is-active': team.factionIds.includes(faction.id) }"
                :style="{ '--chip-color': faction.color || undefined }"
                :aria-pressed="team.factionIds.includes(faction.id)"
                @click="toggleFaction(team, faction.id)"
              >
                {{ faction.name }}
              </button>
            </div>
            <p class="life-counter__hint">{{ t('tools.lifeCounter.factionsHint') }}</p>
          </div>

          <!-- Un hueco por héroe; los ya elegidos desaparecen del resto -->
          <div class="life-counter__slots">
            <BaseSelect
              v-for="(slot, slotIndex) in team.slots"
              :key="slotIndex"
              :model-value="slot"
              :label="t('tools.lifeCounter.heroSlot', { n: slotIndex + 1 })"
              :placeholder="t('tools.lifeCounter.selectHero')"
              :options="slotOptions(team, slotIndex)"
              @update:model-value="(value) => (team.slots[slotIndex] = String(value))"
            />
            <p v-if="poolLoading(team)" class="life-counter__hint" role="status">
              {{ t('tools.lifeCounter.loadingHeroes') }}
            </p>
          </div>
        </section>
      </div>

      <div class="life-counter__actions">
        <BaseButton :disabled="!canStart || starting" @click="startMatch">
          <template #icon><Play :size="16" /></template>
          {{ t(starting ? 'tools.lifeCounter.starting' : 'tools.lifeCounter.start') }}
        </BaseButton>
        <p v-if="startError" class="life-counter__error">
          {{ t('tools.lifeCounter.startError') }}
        </p>
        <p v-else-if="!canStart" class="life-counter__hint">
          {{ t('tools.lifeCounter.startHint') }}
        </p>
      </div>

      <!-- Partidas anteriores (discreto, solo con sesión): retomar la activa
           o consultar las terminadas (solo lectura) -->
      <section v-if="auth.isAuthenticated" class="life-counter__history">
        <button
          type="button"
          class="life-counter__history-toggle"
          :aria-expanded="historyOpen"
          @click="historyOpen = !historyOpen"
        >
          {{ t('tools.lifeCounter.history.title') }}
          <ChevronDown :size="16" :class="{ 'is-open': historyOpen }" />
        </button>

        <div v-if="historyOpen" class="life-counter__history-body">
          <p v-if="historyLoading" class="life-counter__hint" role="status">
            {{ t('tools.lifeCounter.history.loading') }}
          </p>
          <p v-else-if="!historyMatches.length" class="life-counter__hint">
            {{ t('tools.lifeCounter.history.empty') }}
          </p>
          <ul v-else class="life-counter__history-list">
            <li v-for="saved in historyMatches" :key="saved.id" class="life-counter__history-item">
              <div class="life-counter__history-meta">
                <span class="life-counter__history-date">{{ matchDate(saved) }}</span>
                <span class="life-counter__history-status" :class="`is-${saved.status}`">
                  {{
                    t(
                      saved.status === 'active'
                        ? 'tools.lifeCounter.history.active'
                        : 'tools.lifeCounter.history.finished',
                    )
                  }}
                </span>
              </div>
              <p class="life-counter__history-summary">{{ matchSummary(saved) }}</p>
              <div class="life-counter__history-actions">
                <BaseButton
                  v-if="saved.status === 'active'"
                  variant="secondary"
                  @click="resumeMatch(saved)"
                >
                  {{ t('tools.lifeCounter.history.resume') }}
                </BaseButton>
                <BaseButton
                  v-else
                  variant="text"
                  @click="viewingId = viewingId === saved.id ? null : saved.id"
                >
                  {{
                    t(
                      viewingId === saved.id
                        ? 'tools.lifeCounter.history.hide'
                        : 'tools.lifeCounter.history.view',
                    )
                  }}
                </BaseButton>
              </div>
              <div v-if="viewingId === saved.id" class="life-counter__history-detail">
                <div
                  v-for="(team, teamIndex) in saved.state.teams"
                  :key="teamIndex"
                  class="life-counter__history-team"
                >
                  <h4>
                    {{
                      t(
                        teamIndex === 0
                          ? 'tools.lifeCounter.myTeam'
                          : 'tools.lifeCounter.rivalTeam',
                      )
                    }}
                  </h4>
                  <ul>
                    <li
                      v-for="hero in team.heroes"
                      :key="hero.id"
                      :class="{ 'is-defeated': hero.lives === 0 }"
                    >
                      {{ hero.name }} · {{ hero.lives }}
                    </li>
                  </ul>
                </div>
              </div>
            </li>
          </ul>
        </div>
      </section>
    </template>

    <!-- Partida: dos columnas SIEMPRE (también en móvil) para ver ambos
         equipos de un vistazo; los héroes se apilan dentro de cada columna -->
    <template v-else-if="match">
      <div class="life-counter__board" :data-wake-lock="wakeLockStatus">
        <section
          v-for="(team, teamIndex) in match.teams"
          :key="teamIndex"
          class="life-counter__side"
        >
          <h2 class="life-counter__team-title">
            {{ t(teamIndex === 0 ? 'tools.lifeCounter.myTeam' : 'tools.lifeCounter.rivalTeam') }}
          </h2>
          <article
            v-for="hero in team.heroes"
            :key="hero.id"
            class="life-hero"
            :class="{ 'is-defeated': hero.lives === 0 }"
          >
            <h3 class="life-hero__name">{{ hero.name }}</h3>
            <img
              v-if="hero.preview"
              class="life-hero__image"
              :src="hero.preview"
              :alt="hero.name"
              loading="lazy"
            />
            <span v-else class="life-hero__fallback" aria-hidden="true">{{ hero.name }}</span>
            <NumericInput
              class="life-hero__lives"
              :model-value="hero.lives"
              :min="0"
              :label="t('tools.lifeCounter.lives')"
              @update:model-value="(value) => setLives(hero, value)"
            />
            <span v-if="hero.lives === 0" class="life-hero__defeated">
              {{ t('tools.lifeCounter.defeated') }}
            </span>
          </article>
        </section>
      </div>

      <!-- Lanzador de dados a mano durante la partida (plegable, discreto);
           mismo patrón de toggle que "Partidas anteriores" -->
      <section class="life-counter__dice">
        <button
          type="button"
          class="life-counter__history-toggle"
          :aria-expanded="diceOpen"
          @click="diceOpen = !diceOpen"
        >
          {{ t('tools.diceRoller.title') }}
          <ChevronDown :size="16" :class="{ 'is-open': diceOpen }" />
        </button>
        <DiceRoller v-if="diceOpen" />
      </section>

      <div class="life-counter__actions">
        <BaseButton variant="danger" @click="finishMatch">
          <template #icon><Square :size="16" /></template>
          {{ t('tools.lifeCounter.end') }}
        </BaseButton>
      </div>
    </template>

    <!-- Diálogo global de confirmación del motor (terminar partida) -->
    <ConfirmDialog />
  </main>
</template>
