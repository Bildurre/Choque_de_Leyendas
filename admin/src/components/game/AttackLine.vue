<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useLocalesStore } from '@/stores/locales'
import type { TaxonomyOption } from '@juego/shared'

// Línea de ataque en texto (sin chips, regla transversal), SIEMPRE en orden
// rango-tipo-subtipo (+ área): el tipo coloreado con el código del admin
// (físico = rojo, mágico = azul). La usan cartas y héroes en listados,
// paneles y singles. Estilos en components/_info-block.scss.
defineProps<{
  range?: TaxonomyOption | null
  type?: 'physical' | 'magical' | null
  subtype?: TaxonomyOption | null
  area?: boolean
}>()

const { t } = useI18n()
const locales = useLocalesStore()

function tr(obj: Record<string, string> | null | undefined) {
  return (
    obj?.[locales.current] || obj?.[locales.defaultLocale] || Object.values(obj || {})[0] || '—'
  )
}
</script>

<template>
  <span class="attack-line">
    <span v-if="range" class="attack-line__part">{{ tr(range.name) }}</span>
    <span v-if="type" class="attack-line__part" :class="`tinted-${type}`">{{
      t(`cards.attackTypes.${type}`)
    }}</span>
    <span v-if="subtype" class="attack-line__part">{{ tr(subtype.name) }}</span>
    <span v-if="area" class="attack-line__part">{{ t('cards.fields.areaChip') }}</span>
  </span>
</template>
