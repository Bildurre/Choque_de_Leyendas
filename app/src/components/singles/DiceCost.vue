<script setup lang="ts">
import DiceIcon from './DiceIcon.vue'

// Coste en dados (x-cost-display del viejo): pinta el `cost_parsed` que
// devuelve la API pública ([{color: 'red'|'green'|'blue', letter}, ...]).
export interface CostDie {
  color: string
  letter: string
}

withDefaults(defineProps<{ cost: CostDie[]; size?: 'xs' | 'sm' | 'md' | 'lg' }>(), {
  size: 'md',
})

const DICE_TYPES = ['red', 'green', 'blue'] as const
type DiceType = (typeof DICE_TYPES)[number]

function diceType(color: string): DiceType {
  return (DICE_TYPES as readonly string[]).includes(color) ? (color as DiceType) : 'red'
}
</script>

<template>
  <span v-if="cost.length" class="cost-display">
    <DiceIcon v-for="(die, i) in cost" :key="i" :type="diceType(die.color)" :size="size" />
  </span>
</template>
