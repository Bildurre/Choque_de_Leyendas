<script setup lang="ts">
import { computed } from 'vue'
import type { HeroRenderData } from '../types'

// Carta de héroe (750x1050 en /_render): port fiel del preview del admin
// viejo (previews/hero.blade.php + _hero-preview.scss). Los iconos de
// atributos llegan resueltos en item.icons (url|null): si faltan, el valor se
// pinta sobre un círculo plano, NUNCA sobre un SVG local.
const props = defineProps<{ item: HeroRenderData; locale: string }>()

// Textos fijos de la propia carta (el resto del payload llega localizado).
const STRINGS: Record<string, Record<string, string>> = {
  area: { es: 'Área', eu: 'Eremua', en: 'Area' },
  gameTitle: { es: 'Espadas de Ceniza', eu: 'Espadas de Ceniza', en: 'Blades of Ash' },
  gameSubtitle: { es: 'Choque de Leyendas', eu: 'Choque de Leyendas', en: 'Clash of Legends' },
}

function t(key: string): string {
  return STRINGS[key]?.[props.locale] ?? STRINGS[key]?.es ?? ''
}

const vars = computed(() => ({
  '--fc': props.item.faction?.color || '#6b7280',
  '--ftx': props.item.faction?.text_is_dark ? '#000000' : '#ffffff',
}))

// Línea de clase bajo el nombre: raza • clase • superclase.
const classLine = computed(() =>
  [props.item.race, props.item.class, props.item.superclass].filter(Boolean).join(' • '),
)

// Atributos en el orden del viejo (health aparte: círculo grande).
const ATTRIBUTE_KEYS = ['agility', 'mental', 'will', 'strength', 'armor'] as const

// Línea de tipos de una habilidad: rango • tipo • subtipo • área.
function abilityTypes(ability: HeroRenderData['abilities'][number]): string {
  const parts = [ability.attack?.range, ability.attack?.type, ability.attack?.subtype]
  if (ability.area) parts.push(t('area'))
  return parts.filter(Boolean).join(' • ')
}
</script>

<!-- eslint-disable vue/no-v-html -- HTML del WYSIWYG propio (sanitizado en servidor) -->
<template>
  <article class="game-card game-card--hero" :style="vars">
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

    <section class="game-card__attrs">
      <div v-for="key in ATTRIBUTE_KEYS" :key="key" class="game-card__attr">
        <img v-if="item.icons[key]" :src="item.icons[key]!" alt="" />
        <span v-else class="game-card__attr-bg" />
        <span class="game-card__attr-value">{{ item.attributes[key] }}</span>
      </div>
      <div class="game-card__attr game-card__attr--health">
        <img v-if="item.icons.health" :src="item.icons.health!" alt="" />
        <span v-else class="game-card__attr-bg" />
        <span class="game-card__attr-value">{{ item.health }}</span>
      </div>
    </section>

    <section class="game-card__box">
      <div v-if="item.passive?.name || item.passive?.description" class="game-card__passives">
        <div class="game-card__passive">
          <span v-if="item.passive.name" class="game-card__passive-name"
            >{{ item.passive.name }}:</span
          >
          <span v-if="item.passive.description" v-html="item.passive.description" />
        </div>
      </div>

      <hr v-if="(item.passive?.name || item.passive?.description) && item.abilities?.length" />

      <div v-if="item.abilities?.length" class="game-card__actives">
        <template v-for="(ability, i) in item.abilities" :key="i">
          <div class="game-card__active">
            <div class="game-card__active-header">
              <div class="game-card__active-info">
                <span class="game-card__active-name">{{ ability.name }}</span>
                <span v-if="abilityTypes(ability)" class="game-card__active-types">
                  {{ abilityTypes(ability) }}
                </span>
              </div>
              <div v-if="ability.cost_parsed?.length" class="game-card__active-cost">
                <span
                  v-for="(die, j) in ability.cost_parsed"
                  :key="j"
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
              v-if="ability.description"
              class="game-card__active-description"
              v-html="ability.description"
            />
          </div>
          <hr v-if="i < item.abilities.length - 1" />
        </template>
      </div>
    </section>

    <footer class="game-card__footer">
      <span class="game-card__footer-title">{{ t('gameTitle') }}:</span>
      <span class="game-card__footer-subtitle">{{ t('gameSubtitle') }}</span>
    </footer>
  </article>
</template>
