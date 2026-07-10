<script setup lang="ts">
import { computed } from 'vue'
import type { CardRenderData } from '../types'

// Carta jugable (750x1050 en /_render): port fiel del preview del admin viejo
// (previews/card.blade.php + _entity-preview.scss/_card-preview.scss). El
// payload llega YA localizado (RENDER-SPEC) y los iconos resueltos por el
// server en item.icons (url|null): si faltan se cae a un dado plano
// coloreado, NUNCA a un asset local.
const props = defineProps<{ item: CardRenderData; locale: string }>()

// Textos fijos de la propia carta (el resto del payload llega localizado).
const STRINGS: Record<string, Record<string, string>> = {
  unique: { es: 'Única', eu: 'Bakarra', en: 'Unique' },
  area: { es: 'Área', eu: 'Eremua', en: 'Area' },
  hands1: { es: '1 mano', eu: 'Esku 1', en: '1 hand' },
  hands2: { es: '2 manos', eu: '2 esku', en: '2 hands' },
  gameTitle: { es: 'Espadas de Ceniza', eu: 'Espadas de Ceniza', en: 'Blades of Ash' },
  gameSubtitle: { es: 'Choque de Leyendas', eu: 'Choque de Leyendas', en: 'Clash of Legends' },
}

function t(key: string): string {
  return STRINGS[key]?.[props.locale] ?? STRINGS[key]?.es ?? ''
}

// Colores de la banda de facción (var CSS; con fallback neutro).
const vars = computed(() => ({
  '--fc': props.item.faction?.color || '#6b7280',
  '--ftx': props.item.faction?.text_is_dark ? '#000000' : '#ffffff',
}))

// Línea de clase bajo el nombre: tipo • subtipo • tipo de equipo • manos.
const classLine = computed(() => {
  const parts = [props.item.type, props.item.subtype, props.item.equipment_type]
  if (props.item.hands) parts.push(t(props.item.hands > 1 ? 'hands2' : 'hands1'))
  return parts.filter(Boolean).join(' • ')
})

// Línea de tipos de ataque: rango • tipo • subtipo • área.
const typesLine = computed(() => {
  const a = props.item.attack
  const parts = [a?.range, a?.type, a?.subtype]
  if (props.item.area) parts.push(t('area'))
  return parts.filter(Boolean).join(' • ')
})
</script>

<!-- eslint-disable vue/no-v-html -- HTML del WYSIWYG propio (sanitizado en servidor) -->
<template>
  <article class="game-card game-card--card" :style="vars">
    <header class="game-card__header">
      <div class="game-card__titles">
        <h2 class="game-card__name">{{ item.name }}</h2>
        <h3 v-if="classLine" class="game-card__class">{{ classLine }}</h3>
      </div>
      <div v-if="item.faction?.icon_url" class="game-card__faction-logo">
        <img :src="item.faction.icon_url" alt="" />
      </div>
    </header>

    <div class="game-card__art">
      <img v-if="item.image" :src="item.image" alt="" />
    </div>

    <div v-if="item.cost_parsed?.length" class="game-card__cost">
      <span
        v-for="(die, i) in item.cost_parsed"
        :key="i"
        class="game-card__die"
        :class="`game-card__die--${die.color}`"
      >
        <img
          v-if="item.icons[`dice-${die.color}`]"
          :src="item.icons[`dice-${die.color}`]!"
          alt=""
        />
        <i v-else>{{ die.letter }}</i>
      </span>
    </div>

    <section class="game-card__box">
      <div v-if="item.is_unique || typesLine" class="game-card__info">
        <span v-if="item.is_unique" class="game-card__unique">{{ t('unique') }}</span>
        <span v-if="typesLine" class="game-card__types">{{ typesLine }}</span>
      </div>
      <div class="game-card__effects">
        <div v-if="item.restriction" class="game-card__restriction" v-html="item.restriction" />
        <hr v-if="item.restriction && item.effect" />
        <div v-if="item.effect" class="game-card__effect" v-html="item.effect" />
      </div>
    </section>

    <footer class="game-card__footer">
      <span class="game-card__footer-title">{{ t('gameTitle') }}:</span>
      <span class="game-card__footer-subtitle">{{ t('gameSubtitle') }}</span>
    </footer>
  </article>
</template>
