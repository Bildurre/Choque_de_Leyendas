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
    <!-- El separador bajo la restricción va corto y centrado (80%) -->
    <hr v-if="hasRestriction && hasEffect" class="card-effect__rule card-effect__rule--short" />
    <div v-if="hasEffect" class="rich-content" v-html="tr(card.effect)" />

    <!-- Habilidad de héroe otorgada, integrada como en el render -->
    <template v-if="card.hero_ability">
      <hr
        v-if="hasRestriction || hasEffect"
        class="card-effect__rule"
        :class="{ 'card-effect__rule--short': hasRestriction && !hasEffect }"
      />
      <!-- Mismo formato que las habilidades del single de héroe: cabecera
           nombre (enlace) + coste + tipado y el efecto debajo. En el panel
           derecho (compacto) el tipado salta a su propia fila por CSS. -->
      <div class="card-effect__ability">
        <p class="card-effect__ability-head">
          <!-- El nombre enlaza al index de habilidades con esta seleccionada
               (mismo patrón que en héroes: el search la deja en la primera
               página y selected la marca) -->
          <strong
            ><RouterLink
              class="hero-link"
              :to="{
                name: 'hero-abilities',
                query: {
                  selected: String(card.hero_ability.id),
                  search: tr(card.hero_ability.name),
                },
              }"
              >{{ tr(card.hero_ability.name) }}</RouterLink
            ></strong
          >
          <CostDice v-if="card.hero_ability.cost" :cost="card.hero_ability.cost" />
          <!-- Rango y subtipo de ataque enlazan a su definición (linked) -->
          <AttackLine
            v-if="
              card.hero_ability.attack_range ||
              card.hero_ability.attack_type ||
              card.hero_ability.attack_subtype ||
              card.hero_ability.area
            "
            linked
            class="card-effect__ability-typing"
            :range="card.hero_ability.attack_range"
            :type="card.hero_ability.attack_type"
            :subtype="card.hero_ability.attack_subtype"
            :area="card.hero_ability.area"
          />
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
