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
  physical: { es: 'Físico', eu: 'Fisikoa', en: 'Physical' },
  magical: { es: 'Mágico', eu: 'Magikoa', en: 'Magical' },
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

// Línea de clase bajo el nombre. Equipo: tipo de carta · tipo de equipo ·
// subtipo de equipo · manos (solo armas). Resto: tipo · subtipo. Separador
// fino (el viejo usaba • pero en Roboto real; aquí, sin esa fuente
// garantizada, el bullet se veía grueso — el punto medio es fino en
// cualquier fuente de reserva).
const classLine = computed(() => {
  const parts = [
    props.item.type,
    props.item.subtype,
    props.item.equipment_type,
    props.item.equipment_subtype,
  ]
  if (props.item.hands) parts.push(t(props.item.hands > 1 ? 'hands2' : 'hands1'))
  return parts.filter(Boolean).join(' · ')
})

// La clave physical|magical llega cruda del server: se localiza aquí.
function attackTypeLabel(type: string | null | undefined): string | null {
  if (!type) return null
  return STRINGS[type]?.[props.locale] ?? STRINGS[type]?.es ?? type
}

// Línea de tipos de ataque: rango · tipo · subtipo · área.
const typesLine = computed(() => {
  const a = props.item.attack
  const parts = [a?.range, attackTypeLabel(a?.type), a?.subtype]
  if (props.item.area) parts.push(t('area'))
  return parts.filter(Boolean).join(' · ')
})

// Tipado de la habilidad otorgada: rango · tipo · subtipo · área.
const abilityTypesLine = computed(() => {
  const ability = props.item.hero_ability
  if (!ability) return ''
  const parts = [
    ability.attack?.range,
    attackTypeLabel(ability.attack?.type),
    ability.attack?.subtype,
  ]
  if (ability.area) parts.push(t('area'))
  return parts.filter(Boolean).join(' · ')
})
</script>

<!-- eslint-disable vue/no-v-html -- HTML del WYSIWYG propio (sanitizado en servidor) -->
<template>
  <div class="game-card-frame">
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
        <div class="game-card__info">
          <span v-if="item.is_unique" class="game-card__unique">{{ t('unique') }}</span>
          <span v-if="typesLine" class="game-card__types">{{ typesLine }}</span>
        </div>
        <div class="game-card__effects">
          <div v-if="item.restriction" class="game-card__restriction" v-html="item.restriction" />
          <hr v-if="item.restriction && item.effect" />
          <div v-if="item.effect" class="game-card__effect" v-html="item.effect" />
        </div>

        <!-- Habilidad de héroe otorgada, como en el preview del viejo:
             nombre + tipado + coste en dados + descripción -->
        <template v-if="item.hero_ability">
          <hr />
          <div class="game-card__active">
            <div class="game-card__active-header">
              <div class="game-card__active-info">
                <span class="game-card__active-name">{{ item.hero_ability.name }}</span>
                <span v-if="abilityTypesLine" class="game-card__active-types">
                  {{ abilityTypesLine }}
                </span>
              </div>
              <div v-if="item.hero_ability.cost_parsed?.length" class="game-card__active-cost">
                <span
                  v-for="(die, i) in item.hero_ability.cost_parsed"
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
            </div>
            <div
              v-if="item.hero_ability.description"
              class="game-card__active-description"
              v-html="item.hero_ability.description"
            />
          </div>
        </template>
      </section>

      <footer class="game-card__footer">
        <!-- Logo del gestor de iconos (slug "faerie", como el hada del viejo):
             si no está subido no se pinta nada, sin hueco. -->
        <img
          v-if="item.icons.faerie"
          class="game-card__footer-logo"
          :src="item.icons.faerie"
          alt=""
        />
        <span class="game-card__footer-title">{{ t('gameTitle') }}:</span>
        <span class="game-card__footer-subtitle">{{ t('gameSubtitle') }}</span>
      </footer>
    </article>
  </div>
</template>
