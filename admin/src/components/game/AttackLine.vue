<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useLocalesStore } from '@/stores/locales'
import type { TaxonomyOption } from '@juego/shared'

// Línea de ataque en texto (sin chips, regla transversal), SIEMPRE en orden
// rango-tipo-subtipo (+ área): el tipo coloreado con el código del admin
// (físico = rojo, mágico = azul). La usan cartas y héroes en listados,
// paneles y singles. Estilos en components/_info-block.scss.
//
// Con `linked`, rango y subtipo enlazan a SU DEFINICIÓN (el index de su
// taxonomía con el elemento seleccionado, patrón selected+search de
// useEntityList). El tipo es un enum sin entidad: siempre texto (tintado).
defineProps<{
  range?: TaxonomyOption | null
  type?: 'physical' | 'magical' | null
  subtype?: TaxonomyOption | null
  area?: boolean
  linked?: boolean
}>()

const { t } = useI18n()
const locales = useLocalesStore()

function tr(obj: Record<string, string> | null | undefined) {
  return (
    obj?.[locales.current] || obj?.[locales.defaultLocale] || Object.values(obj || {})[0] || '—'
  )
}

/** Enlace a la definición del término: su index con selected+search. */
function definitionLink(routeName: string, option: TaxonomyOption) {
  return { name: routeName, query: { selected: String(option.id), search: tr(option.name) } }
}
</script>

<template>
  <span class="attack-line">
    <RouterLink
      v-if="range && linked"
      class="attack-line__part hero-link"
      :title="t('cards.fields.attackRange')"
      :to="definitionLink('attack-ranges', range)"
      >{{ tr(range.name) }}</RouterLink
    >
    <span v-else-if="range" class="attack-line__part">{{ tr(range.name) }}</span>
    <span v-if="type" class="attack-line__part" :class="`tinted-${type}`">{{
      t(`cards.attackTypes.${type}`)
    }}</span>
    <RouterLink
      v-if="subtype && linked"
      class="attack-line__part hero-link"
      :title="t('cards.fields.attackSubtype')"
      :to="definitionLink('attack-subtypes', subtype)"
      >{{ tr(subtype.name) }}</RouterLink
    >
    <span v-else-if="subtype" class="attack-line__part">{{ tr(subtype.name) }}</span>
    <span v-if="area" class="attack-line__part">{{ t('cards.fields.areaChip') }}</span>
  </span>
</template>
