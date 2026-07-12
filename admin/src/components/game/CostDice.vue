<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useIconsStore } from '@/stores/icons'

// Coste "RGB" pintado en línea: icono del gestor de Iconos del motor
// (dice-red/dice-green/dice-blue) sin fondo ni padding; si el icono no está
// subido, fallback a la letra coloreada (R roja, G verde, B azul).
const props = withDefaults(
  defineProps<{
    /** Coste como string de dados R/G/B. */
    cost: string
    size?: 'small' | 'medium'
  }>(),
  { size: 'small' },
)

const icons = useIconsStore()
onMounted(() => {
  icons.load().catch(() => {})
})

const DICE: Record<string, { color: string; icon: string }> = {
  R: { color: 'red', icon: 'dice-red' },
  G: { color: 'green', icon: 'dice-green' },
  B: { color: 'blue', icon: 'dice-blue' },
}

const dice = computed(() =>
  (props.cost || '')
    .toUpperCase()
    .split('')
    .filter((letter) => DICE[letter])
    .map((letter) => ({ letter, ...DICE[letter] })),
)

/** URL del icono del gestor (por slug o nombre), o null → letra coloreada. */
function iconUrl(name: string): string | null {
  return icons.icons.find((i) => i.slug === name || i.name === name)?.url ?? null
}
</script>

<template>
  <span
    v-if="dice.length"
    class="cost-dice"
    :class="`cost-dice--${size}`"
    role="img"
    :aria-label="cost"
  >
    <span
      v-for="(d, i) in dice"
      :key="`${d.letter}-${i}`"
      class="cost-dice__die"
      :class="`cost-dice__die--${d.color}`"
    >
      <img v-if="iconUrl(d.icon)" :src="iconUrl(d.icon)!" alt="" />
      <template v-else>{{ d.letter }}</template>
    </span>
  </span>
</template>
