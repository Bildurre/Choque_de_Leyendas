<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { BaseButton, BaseSelect, NumericInput, useConfirm } from '@edc-motor/ui'
import { Ban, Dices, Lock, Trash2 } from '@lucide/vue'
import { api } from '@/lib/api'
import { DIE_COLORS, pickRandomIndices, rollDie, type DieColor } from '@/lib/dice'

// Lanzador de dados de acción (R/G/B, caras 3R/2G/1B — ver lib/dice.ts).
// Componente REUTILIZABLE pensado para MÓVIL: vive en su propia vista
// (/es/herramientas/lanzador-de-dados) y embebido en el contador de vidas.
// Máquina de estados en tres fases:
//  - prep: eliges cuántos dados y puedes fijar el color de algunos
//    (guardados: no se lanzan); "Lanzar" tira solo los no guardados.
//  - result: relanzamiento tipo Yahtzee — tocar un dado lo guarda/suelta y
//    "Relanzar" tira los no guardados, tantas veces como quieras; "Salvar el
//    resultado" consolida y pasa al juego.
//  - game: sobre el resultado consolidado, tocar un dado abre su panel de
//    acciones (relanzar / gastar / cambiar color; los gastados: recuperar
//    relanzando / manteniendo / eligiendo color) y hay acciones en lote
//    (relanzar X aleatorios no gastados, recuperar X gastados aleatorios).
// El panel de acciones va EN FLUJO bajo la bandeja (nada flotante: en móvil
// funciona como un bottom-sheet anclado y no tapa los dados). El estado
// COMPLETO vive en localStorage bajo una única clave: la vista propia y el
// embebido en el contador son EL MISMO lanzador (uno solo, conceptualmente).
// El host pone el <ConfirmDialog /> del motor (aquí no, para no duplicarlo
// dentro del contador de vidas, que ya lo tiene).

type Phase = 'prep' | 'result' | 'game'

interface RollerDie {
  id: number
  color: DieColor | null // null solo en preparación (aún sin resultado)
  held: boolean // prep/result: fijado, no entra en el lanzamiento
  spent: boolean // game: gastado (visible pero fuera de los relanzamientos)
}

type RecoverMode = 'reroll' | 'keep' | 'choose'

const DEFAULT_DICE = 5 // los dados de acción habituales de una partida
const MAX_DICE = 10
const STORAGE_KEY = 'cdl_dice_roller'

const { t } = useI18n()
const { confirm } = useConfirm()

const phase = ref<Phase>('prep')
const count = ref(DEFAULT_DICE)
const dice = ref<RollerDie[]>([])
const selectedId = ref<number | null>(null)
let nextId = 1

function makeDie(): RollerDie {
  return { id: nextId++, color: null, held: false, spent: false }
}

// --- Persistencia: una única clave compartida entre los dos montajes ---

function persist() {
  localStorage.setItem(
    STORAGE_KEY,
    JSON.stringify({ phase: phase.value, count: count.value, dice: dice.value }),
  )
}

function restore(): boolean {
  const saved = localStorage.getItem(STORAGE_KEY)
  if (!saved) return false
  try {
    const parsed = JSON.parse(saved) as { phase?: Phase; count?: number; dice?: RollerDie[] }
    if (!['prep', 'result', 'game'].includes(parsed.phase ?? '')) return false
    if (!Array.isArray(parsed.dice) || !parsed.dice.length) return false
    const clean = parsed.dice.map((die) => ({
      id: Number(die.id),
      color: (DIE_COLORS as readonly string[]).includes(String(die.color))
        ? (die.color as DieColor)
        : null,
      held: !!die.held,
      spent: !!die.spent,
    }))
    phase.value = parsed.phase as Phase
    count.value = Math.min(MAX_DICE, Math.max(1, Number(parsed.count) || clean.length))
    dice.value = clean
    nextId = Math.max(0, ...clean.map((die) => die.id)) + 1
    selectedId.value = null
    return true
  } catch {
    localStorage.removeItem(STORAGE_KEY)
    return false
  }
}

// Cada cambio persiste (los dos montajes nunca están vivos a la vez: rutas
// distintas; el evento storage cubre además otras pestañas abiertas).
watch([phase, count, dice], persist, { deep: true })

function onStorage(event: StorageEvent) {
  if (event.key !== STORAGE_KEY) return
  if (!restore()) reset()
}

// --- Iconos de los dados del gestor (compartidos entre montajes) ---

// url|null por clave dice-red|dice-green|dice-blue; sin subir (o sin red):
// fallback CSS (cuadrado redondeado con el color, como .cost-colors).
const diceIcons = reactive<Record<string, string | null>>({})

async function loadIcons() {
  try {
    const { data } = await api.get('/cards/filters')
    Object.assign(diceIcons, data?.icons ?? {})
  } catch {
    // sin red: el fallback pinta los dados igual
  }
}

function iconFor(color: DieColor): string | null {
  return diceIcons[`dice-${color}`] ?? null
}

// --- Tirar: todo pasa por rollDie() (lib/dice.ts) ---

// Animación breve del lanzamiento: ids en vuelo → clase .is-rolling (CSS
// respeta prefers-reduced-motion).
const rollingIds = ref<Set<number>>(new Set())
let rollingTimer: ReturnType<typeof setTimeout> | null = null

function rollGroup(target: RollerDie[]) {
  for (const die of target) die.color = rollDie()
  rollingIds.value = new Set(target.map((die) => die.id))
  if (rollingTimer) clearTimeout(rollingTimer)
  rollingTimer = setTimeout(() => {
    rollingIds.value = new Set()
    rollingTimer = null
  }, 500)
}

// --- Fase preparación ---

// El input de nº de dados solo existe en preparación: los ya configurados
// que quepan se conservan (como los huecos del contador de vidas).
watch(count, (value) => {
  if (phase.value !== 'prep') return
  dice.value = Array.from({ length: value }, (_, i) => dice.value[i] ?? makeDie())
  if (!dice.value.some((die) => die.id === selectedId.value)) selectedId.value = null
})

/** Fijar color en preparación = elegirlo Y guardarlo (queda fuera del lanzamiento). */
function fixColor(die: RollerDie, color: DieColor) {
  die.color = color
  die.held = true
  selectedId.value = null
}

function release(die: RollerDie) {
  die.held = false
  selectedId.value = null
}

function roll() {
  rollGroup(dice.value.filter((die) => !die.held))
  phase.value = 'result'
  selectedId.value = null
}

// --- Fase resultado (iterativa) ---

function reroll() {
  rollGroup(dice.value.filter((die) => !die.held))
}

function saveResult() {
  for (const die of dice.value) die.held = false
  phase.value = 'game'
  selectedId.value = null
}

// --- Fase juego ---

const selectedDie = computed(() => dice.value.find((die) => die.id === selectedId.value) ?? null)
const heldCount = computed(() => dice.value.filter((die) => die.held).length)
const activeDice = computed(() => dice.value.filter((die) => !die.spent))
const spentDice = computed(() => dice.value.filter((die) => die.spent))

function rerollOne(die: RollerDie) {
  rollGroup([die])
  selectedId.value = null
}

function spend(die: RollerDie) {
  die.spent = true
  selectedId.value = null
}

function changeColor(die: RollerDie, color: DieColor) {
  die.color = color
  selectedId.value = null
}

function recover(die: RollerDie, mode: RecoverMode, color?: DieColor) {
  die.spent = false
  if (mode === 'reroll') rollGroup([die])
  else if (mode === 'choose' && color) die.color = color
  selectedId.value = null
}

// Acciones en lote: X aleatorios. El relanzamiento nunca toca gastados; la
// recuperación solo gastados. En modo "elegir color" el color elegido en el
// select se aplica a TODOS los recuperados (decisión de usabilidad: elegir
// dado a dado sobre una selección aleatoria alargaba el flujo sin aportar).
const rerollX = ref(1)
const recoverX = ref(1)
const recoverColor = ref<DieColor>('red')

// Dominio cerrado con setter que sanea (el BaseSelect del motor emite
// string, como el <select> nativo): mismo patrón que los filtros de cartas.
const recoverModeRaw = ref<RecoverMode>('reroll')
const recoverMode = computed({
  get: () => recoverModeRaw.value,
  set: (value: string) => {
    recoverModeRaw.value = ['reroll', 'keep', 'choose'].includes(value)
      ? (value as RecoverMode)
      : 'reroll'
  },
})

const recoverModeOptions = computed(() => [
  { value: 'reroll', label: t('tools.diceRoller.recoverReroll') },
  { value: 'keep', label: t('tools.diceRoller.recoverKeep') },
  { value: 'choose', label: t('tools.diceRoller.recoverChoose') },
])

function randomReroll() {
  const pool = activeDice.value
  rollGroup(pickRandomIndices(pool.length, rerollX.value).map((i) => pool[i]))
  selectedId.value = null
}

function randomRecover() {
  const pool = spentDice.value
  const picked = pickRandomIndices(pool.length, recoverX.value).map((i) => pool[i])
  for (const die of picked) die.spent = false
  if (recoverMode.value === 'reroll') rollGroup(picked)
  else if (recoverMode.value === 'choose') {
    for (const die of picked) die.color = recoverColor.value
  }
  selectedId.value = null
}

// --- Limpiar: vuelta a la preparación (con confirmación) ---

function reset() {
  dice.value = Array.from({ length: count.value }, makeDie)
  phase.value = 'prep'
  selectedId.value = null
}

async function clearAll() {
  const confirmed = await confirm({
    title: t('tools.diceRoller.clearConfirmTitle'),
    message: t('tools.diceRoller.clearConfirmMessage'),
    confirmLabel: t('tools.diceRoller.clearConfirm'),
    cancelLabel: t('tools.diceRoller.cancel'),
    variant: 'danger',
  })
  if (confirmed) reset()
}

// --- Interacción con un dado (según fase) ---

function onDieTap(die: RollerDie) {
  if (phase.value === 'result') {
    // En resultado la única acción es guardar/soltar: toggle directo (un
    // tap, sin panel; es el gesto Yahtzee que se repite más).
    die.held = !die.held
    return
  }
  selectedId.value = selectedId.value === die.id ? null : die.id
}

function dieLabel(die: RollerDie, index: number): string {
  const parts = [t('tools.diceRoller.die', { n: index + 1 })]
  parts.push(die.color ? t(`tools.diceRoller.colors.${die.color}`) : t('tools.diceRoller.noResult'))
  if (die.held) parts.push(t('tools.diceRoller.held'))
  if (die.spent) parts.push(t('tools.diceRoller.spent'))
  return parts.join(', ')
}

// --- Ciclo de vida ---

onMounted(() => {
  window.addEventListener('storage', onStorage)
  if (!restore()) reset()
  void loadIcons()
})

onUnmounted(() => {
  window.removeEventListener('storage', onStorage)
  if (rollingTimer) {
    clearTimeout(rollingTimer)
    rollingTimer = null
  }
})
</script>

<template>
  <section class="dice-roller" :data-phase="phase">
    <!-- Preparación: nº de dados + hint -->
    <div v-if="phase === 'prep'" class="dice-roller__config">
      <NumericInput
        v-model="count"
        class="dice-roller__count"
        :label="t('tools.diceRoller.diceCount')"
        :min="1"
        :max="MAX_DICE"
      />
      <p class="dice-roller__hint">{{ t('tools.diceRoller.prepHint') }}</p>
    </div>
    <p v-else-if="phase === 'result'" class="dice-roller__hint">
      {{ t('tools.diceRoller.resultHint') }}
    </p>
    <p v-else class="dice-roller__hint">{{ t('tools.diceRoller.gameHint') }}</p>

    <!-- Bandeja: los dados, grandes y táctiles -->
    <div class="dice-roller__tray" role="group" :aria-label="t('tools.diceRoller.title')">
      <button
        v-for="(die, index) in dice"
        :key="die.id"
        type="button"
        class="dice-roller__die"
        :class="[
          die.color ? `dice-roller__die--${die.color}` : 'dice-roller__die--blank',
          {
            'is-held': die.held,
            'is-spent': die.spent,
            'is-selected': selectedId === die.id,
            'is-rolling': rollingIds.has(die.id),
          },
        ]"
        :aria-pressed="selectedId === die.id || (phase === 'result' && die.held)"
        :aria-label="dieLabel(die, index)"
        :data-die="die.id"
        :data-color="die.color ?? 'none'"
        :data-held="die.held"
        :data-spent="die.spent"
        @click="onDieTap(die)"
      >
        <img
          v-if="die.color && iconFor(die.color)"
          :src="iconFor(die.color)!"
          alt=""
          class="dice-roller__icon"
        />
        <span v-else-if="die.color" class="dice-roller__fill" aria-hidden="true"></span>
        <span v-else class="dice-roller__blank" aria-hidden="true">?</span>
        <span v-if="die.held" class="dice-roller__badge dice-roller__badge--held">
          <Lock :size="12" />
        </span>
        <span v-if="die.spent" class="dice-roller__badge dice-roller__badge--spent">
          <Ban :size="12" />
        </span>
      </button>
    </div>

    <!-- Panel de acciones del dado seleccionado (en flujo, bajo la bandeja) -->
    <div v-if="selectedDie && phase === 'prep'" class="dice-roller__panel">
      <span class="dice-roller__panel-title">{{ t('tools.diceRoller.setColor') }}</span>
      <div class="dice-roller__panel-actions">
        <button
          v-for="color in DIE_COLORS"
          :key="color"
          type="button"
          class="dice-roller__swatch"
          :class="`dice-roller__swatch--${color}`"
          :aria-label="t(`tools.diceRoller.colors.${color}`)"
          :title="t(`tools.diceRoller.colors.${color}`)"
          @click="fixColor(selectedDie, color)"
        >
          <img v-if="iconFor(color)" :src="iconFor(color)!" alt="" class="dice-roller__icon" />
          <span v-else class="dice-roller__fill" aria-hidden="true"></span>
        </button>
        <BaseButton v-if="selectedDie.held" variant="secondary" @click="release(selectedDie)">
          {{ t('tools.diceRoller.release') }}
        </BaseButton>
      </div>
    </div>

    <div
      v-else-if="selectedDie && phase === 'game' && !selectedDie.spent"
      class="dice-roller__panel"
    >
      <span class="dice-roller__panel-title">
        {{ dieLabel(selectedDie, dice.indexOf(selectedDie)) }}
      </span>
      <div class="dice-roller__panel-actions">
        <BaseButton variant="secondary" @click="rerollOne(selectedDie)">
          <template #icon><Dices :size="16" /></template>
          {{ t('tools.diceRoller.rerollOne') }}
        </BaseButton>
        <BaseButton variant="secondary" @click="spend(selectedDie)">
          <template #icon><Ban :size="16" /></template>
          {{ t('tools.diceRoller.spend') }}
        </BaseButton>
      </div>
      <span class="dice-roller__panel-subtitle">{{ t('tools.diceRoller.changeColor') }}</span>
      <div class="dice-roller__panel-actions">
        <button
          v-for="color in DIE_COLORS"
          :key="color"
          type="button"
          class="dice-roller__swatch"
          :class="`dice-roller__swatch--${color}`"
          :aria-label="t(`tools.diceRoller.colors.${color}`)"
          :title="t(`tools.diceRoller.colors.${color}`)"
          @click="changeColor(selectedDie, color)"
        >
          <img v-if="iconFor(color)" :src="iconFor(color)!" alt="" class="dice-roller__icon" />
          <span v-else class="dice-roller__fill" aria-hidden="true"></span>
        </button>
      </div>
    </div>

    <div v-else-if="selectedDie && phase === 'game'" class="dice-roller__panel">
      <span class="dice-roller__panel-title">{{ t('tools.diceRoller.recover') }}</span>
      <div class="dice-roller__panel-actions">
        <BaseButton variant="secondary" @click="recover(selectedDie, 'reroll')">
          <template #icon><Dices :size="16" /></template>
          {{ t('tools.diceRoller.recoverReroll') }}
        </BaseButton>
        <BaseButton variant="secondary" @click="recover(selectedDie, 'keep')">
          {{ t('tools.diceRoller.recoverKeep') }}
        </BaseButton>
      </div>
      <span class="dice-roller__panel-subtitle">{{ t('tools.diceRoller.recoverChoose') }}</span>
      <div class="dice-roller__panel-actions">
        <button
          v-for="color in DIE_COLORS"
          :key="color"
          type="button"
          class="dice-roller__swatch"
          :class="`dice-roller__swatch--${color}`"
          :aria-label="t(`tools.diceRoller.colors.${color}`)"
          :title="t(`tools.diceRoller.colors.${color}`)"
          @click="recover(selectedDie, 'choose', color)"
        >
          <img v-if="iconFor(color)" :src="iconFor(color)!" alt="" class="dice-roller__icon" />
          <span v-else class="dice-roller__fill" aria-hidden="true"></span>
        </button>
      </div>
    </div>

    <!-- Acciones de la fase -->
    <div v-if="phase === 'prep'" class="dice-roller__actions">
      <BaseButton @click="roll">
        <template #icon><Dices :size="16" /></template>
        {{ t('tools.diceRoller.roll') }}
      </BaseButton>
    </div>

    <div v-else-if="phase === 'result'" class="dice-roller__actions">
      <BaseButton :disabled="heldCount === dice.length" @click="reroll">
        <template #icon><Dices :size="16" /></template>
        {{ t('tools.diceRoller.reroll') }}
      </BaseButton>
      <BaseButton variant="secondary" @click="saveResult">
        {{ t('tools.diceRoller.saveResult') }}
      </BaseButton>
      <BaseButton variant="text" @click="clearAll">
        <template #icon><Trash2 :size="16" /></template>
        {{ t('tools.diceRoller.clear') }}
      </BaseButton>
    </div>

    <template v-else>
      <!-- Acciones en lote sobre X dados aleatorios -->
      <div class="dice-roller__batch">
        <NumericInput
          v-model="rerollX"
          class="dice-roller__batch-count"
          :label="t('tools.diceRoller.randomCount')"
          :min="1"
          :max="Math.max(1, activeDice.length)"
        />
        <BaseButton variant="secondary" :disabled="!activeDice.length" @click="randomReroll">
          <template #icon><Dices :size="16" /></template>
          {{ t('tools.diceRoller.randomReroll') }}
        </BaseButton>
      </div>
      <div class="dice-roller__batch">
        <NumericInput
          v-model="recoverX"
          class="dice-roller__batch-count"
          :label="t('tools.diceRoller.randomCount')"
          :min="1"
          :max="Math.max(1, spentDice.length)"
        />
        <BaseSelect
          v-model="recoverMode"
          class="dice-roller__batch-mode"
          :label="t('tools.diceRoller.recoverMode')"
          :options="recoverModeOptions"
        />
        <div
          v-if="recoverMode === 'choose'"
          class="dice-roller__panel-actions"
          role="radiogroup"
          :aria-label="t('tools.diceRoller.recoverColor')"
        >
          <button
            v-for="color in DIE_COLORS"
            :key="color"
            type="button"
            role="radio"
            class="dice-roller__swatch"
            :class="[`dice-roller__swatch--${color}`, { 'is-active': recoverColor === color }]"
            :aria-checked="recoverColor === color"
            :aria-label="t(`tools.diceRoller.colors.${color}`)"
            :title="t(`tools.diceRoller.colors.${color}`)"
            @click="recoverColor = color"
          >
            <img v-if="iconFor(color)" :src="iconFor(color)!" alt="" class="dice-roller__icon" />
            <span v-else class="dice-roller__fill" aria-hidden="true"></span>
          </button>
        </div>
        <BaseButton variant="secondary" :disabled="!spentDice.length" @click="randomRecover">
          {{ t('tools.diceRoller.randomRecover') }}
        </BaseButton>
      </div>
      <div class="dice-roller__actions">
        <BaseButton variant="text" @click="clearAll">
          <template #icon><Trash2 :size="16" /></template>
          {{ t('tools.diceRoller.clear') }}
        </BaseButton>
      </div>
    </template>
  </section>
</template>
