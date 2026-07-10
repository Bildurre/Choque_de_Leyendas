<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { Delete } from '@lucide/vue'
import { useIconsStore } from '@/stores/icons'

// Widget compartido de coste en dados (R/G/B): valor de solo lectura +
// botones que añaden dados (máx. 5) + botón que quita el último. Se muestra
// siempre normalizado R→G→B (misma regla que HasCost en la api). Los iconos
// de dado salen del gestor de Iconos del motor (dice-red/dice-green/dice-blue);
// si faltan, fallback a botón de color plano con la letra.
const props = withDefaults(
  defineProps<{
    modelValue: string
    label?: string
    error?: string
    /** Máximo de dados (== HasCost::COST_MAX). */
    max?: number
    /** Etiqueta accesible del botón de borrar (pásala traducida). */
    removeLabel?: string
  }>(),
  { max: 5 },
)

const emit = defineEmits<{ 'update:modelValue': [string] }>()

const icons = useIconsStore()
onMounted(() => {
  icons.load().catch(() => {})
})

const DICE = [
  { letter: 'R', color: 'red', icon: 'dice-red' },
  { letter: 'G', color: 'green', icon: 'dice-green' },
  { letter: 'B', color: 'blue', icon: 'dice-blue' },
] as const

type Die = (typeof DICE)[number]

/** Normalización visual: mayúsculas, solo R/G/B, orden R→G→B. */
function normalize(value: string): string {
  const up = (value || '').toUpperCase()
  const count = (letter: string) => up.split('').filter((c) => c === letter).length
  return 'R'.repeat(count('R')) + 'G'.repeat(count('G')) + 'B'.repeat(count('B'))
}

const cost = computed(() => normalize(props.modelValue))
const dice = computed<Die[]>(
  () => cost.value.split('').map((letter) => DICE.find((d) => d.letter === letter)) as Die[],
)

/** URL del icono del gestor (por slug o nombre), o null → fallback. */
function iconUrl(name: string): string | null {
  return icons.icons.find((i) => i.slug === name || i.name === name)?.url ?? null
}

function add(letter: string) {
  if (cost.value.length >= props.max) return
  emit('update:modelValue', normalize(cost.value + letter))
}

function removeLast() {
  if (!cost.value.length) return
  emit('update:modelValue', cost.value.slice(0, -1))
}
</script>

<template>
  <div class="cost-input" :class="{ 'cost-input--error': !!error }">
    <span v-if="label" class="cost-input__label">{{ label }}</span>

    <div class="cost-input__row">
      <!-- Valor de solo lectura: los dados ya normalizados R→G→B -->
      <div class="cost-input__value" aria-readonly="true">
        <template v-if="dice.length">
          <span
            v-for="(d, i) in dice"
            :key="`${d.letter}-${i}`"
            class="cost-input__die"
            :class="`cost-input__die--${d.color}`"
          >
            <img v-if="iconUrl(d.icon)" :src="iconUrl(d.icon)!" :alt="d.letter" />
            <template v-else>{{ d.letter }}</template>
          </span>
        </template>
        <span v-else class="cost-input__placeholder">—</span>
      </div>

      <div class="cost-input__buttons">
        <button
          v-for="d in DICE"
          :key="d.letter"
          type="button"
          class="cost-input__btn"
          :class="`cost-input__btn--${d.color}`"
          :disabled="cost.length >= max"
          :aria-label="d.letter"
          @click="add(d.letter)"
        >
          <img v-if="iconUrl(d.icon)" :src="iconUrl(d.icon)!" :alt="d.letter" />
          <template v-else>{{ d.letter }}</template>
        </button>
        <button
          type="button"
          class="cost-input__btn cost-input__btn--remove"
          :disabled="!cost.length"
          :aria-label="removeLabel || 'Borrar'"
          :title="removeLabel || 'Borrar'"
          @click="removeLast"
        >
          <Delete :size="16" />
        </button>
      </div>
    </div>

    <p v-if="error" class="cost-input__error">{{ error }}</p>
  </div>
</template>
