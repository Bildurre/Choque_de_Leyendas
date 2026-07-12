<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DiceCost, { type CostDie } from './DiceCost.vue'

// Tarjeta de habilidad (x-entity-show.ability-card del viejo): pasivas solo
// nombre + descripción; activas añaden coste en dados y la línea de tipos
// "rango - tipo - subtipo - área". La descripción llega como HTML del
// wysiwyg propio, saneado en servidor.
export interface AbilityAttack {
  type: string | null
  range: string | null
  subtype: string | null
}

const props = withDefaults(
  defineProps<{
    variant?: 'active' | 'passive'
    name: string
    description: string
    cost?: CostDie[]
    attack?: AbilityAttack | null
    area?: boolean
  }>(),
  { variant: 'active', cost: () => [], attack: null, area: false },
)

const { t } = useI18n()

// Línea de tipos del viejo: solo si hay rango Y subtipo.
const typesLine = computed(() => {
  if (!props.attack?.range || !props.attack?.subtype) return ''
  const parts = [props.attack.range]
  if (props.attack.type) parts.push(t(`singles.attackTypes.${props.attack.type}`))
  parts.push(props.attack.subtype)
  if (props.area) parts.push(t('singles.card.area'))
  return parts.join(' - ')
})
</script>

<!-- eslint-disable vue/no-v-html -- HTML del wysiwyg propio, saneado en servidor -->
<template>
  <div class="ability-item" :class="`ability-item--${variant}`">
    <div v-if="variant === 'active'" class="ability-item__header">
      <h4 class="ability-item__name">{{ name }}</h4>
      <DiceCost v-if="cost.length" class="ability-item__cost" :cost="cost" size="sm" />
      <p v-if="typesLine" class="ability-item__types">{{ typesLine }}</p>
    </div>
    <h4 v-else class="ability-item__name">{{ name }}</h4>
    <div class="ability-item__description rich-content" v-html="description" />
  </div>
</template>
