<script setup lang="ts">
import { computed } from 'vue'
import { useLocalesStore } from '@/stores/locales'
import type { Card } from '@juego/shared'
import CostDice from '@/components/game/CostDice.vue'
import AttackLine from '@/components/game/AttackLine.vue'

// Efecto de una carta como en la preview (CardRender): restricción, efecto
// y, al pie, la habilidad de héroe otorgada INTEGRADA (nombre + línea de
// ataque + coste + descripción). Lo comparten el panel derecho del listado
// y el single. Estilos en views/_cards.scss.
const props = defineProps<{ card: Card }>()

const locales = useLocalesStore()

function tr(obj: Record<string, string> | null | undefined) {
  return (
    obj?.[locales.current] || obj?.[locales.defaultLocale] || Object.values(obj || {})[0] || '—'
  )
}

const hasRestriction = computed(() => tr(props.card.restriction) !== '—')
const hasEffect = computed(() => tr(props.card.effect) !== '—')
</script>

<!-- eslint-disable vue/no-v-html -- HTML del WYSIWYG propio (sanitización en servidor) -->
<template>
  <div class="card-effect">
    <div
      v-if="hasRestriction"
      class="card-effect__restriction rich-content"
      v-html="tr(card.restriction)"
    />
    <hr v-if="hasRestriction && hasEffect" class="card-effect__rule" />
    <div v-if="hasEffect" class="rich-content" v-html="tr(card.effect)" />

    <!-- Habilidad de héroe otorgada, integrada como en el render -->
    <template v-if="card.hero_ability">
      <hr v-if="hasRestriction || hasEffect" class="card-effect__rule" />
      <div class="card-effect__ability">
        <p class="card-effect__ability-header">
          <span class="card-effect__ability-info">
            <strong>{{ tr(card.hero_ability.name) }}</strong>
            <AttackLine
              :range="card.hero_ability.attack_range"
              :type="card.hero_ability.attack_type"
              :subtype="card.hero_ability.attack_subtype"
              :area="card.hero_ability.area"
            />
          </span>
          <CostDice v-if="card.hero_ability.cost" :cost="card.hero_ability.cost" />
        </p>
        <div
          v-if="tr(card.hero_ability.description) !== '—'"
          class="rich-content"
          v-html="tr(card.hero_ability.description)"
        />
      </div>
    </template>
  </div>
</template>
