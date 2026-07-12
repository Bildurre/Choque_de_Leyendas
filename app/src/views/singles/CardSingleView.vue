<script setup lang="ts">
import { computed, nextTick, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { BlockQuote } from '@edc-motor/ui'
import AbilityCard, { type AbilityAttack } from '@/components/singles/AbilityCard.vue'
import CatalogRelated from '@/components/singles/CatalogRelated.vue'
import DiceCost, { type CostDie } from '@/components/singles/DiceCost.vue'
import InfoBlock from '@/components/singles/InfoBlock.vue'
import { applyOgMeta } from '@/entities/singleOg'
import { sectionDetailRoute } from '@/entities/singleRoutes'

// Single de carta (portado de public/cards/show.blade.php del viejo): PNG
// grande + info-blocks ("Detalles de la Carta", "Detalles del Ataque",
// "Efectos"), cita épica y relateds de cartas. Lo monta EntityDetailView
// (banner, fondo de página, añadir a la colección y head SEO); aquí solo la
// ficha + og. Los enlaces a índices filtrados del viejo se portan como
// texto plano (el catálogo nuevo no tiene esos filtros, CONVENTIONS2 §9.1).
interface FactionRef {
  id: number
  name: string
  slug: string | null
  color: string
  text_is_dark: boolean
}

interface GrantedAbility {
  id: number
  name: string
  description: string
  cost_parsed: CostDie[]
  attack: AbilityAttack
  area: boolean
}

interface CardPayload {
  id: number
  name: Record<string, string>
  slug: Record<string, string>
  image: string | null
  preview: string | null
  faction: FactionRef | null
  type: {
    name: string
    superclass: string | null
    allows_subtypes: boolean
    is_equipment: boolean
  } | null
  subtype: string | null
  equipment: { type: string | null; hands: number | null } | null
  cost: string | null
  cost_parsed: CostDie[]
  is_unique: boolean
  attack: { type: string | null; range: string | null; subtype: string | null; area: boolean }
  effect: string
  restriction: string
  granted_ability: GrantedAbility | null
  epic_quote: string
}

const props = defineProps<{ item: CardPayload; locale: string }>()

const { t } = useI18n()

const name = computed(
  () => props.item.name[props.locale] || Object.values(props.item.name)[0] || '',
)

const factionRoute = computed(() =>
  props.item.faction ? sectionDetailRoute('factions', props.item.faction.slug, props.locale) : null,
)

const hasAttack = computed(() => {
  const attack = props.item.attack
  return Boolean(attack?.range || attack?.type || attack?.subtype || attack?.area)
})

const hasEffects = computed(() =>
  Boolean(props.item.restriction || props.item.effect || props.item.granted_ability),
)

// La cita puede llegar como texto plano (el viejo la envolvía en <p>).
const quoteHtml = computed(() => {
  const quote = props.item.epic_quote?.trim() ?? ''
  if (!quote) return ''
  return quote.startsWith('<') ? quote : `<p>${quote}</p>`
})

// og:* tras el head de EntityDetailView (mismo tick de render).
watch(
  () => props.item,
  async () => {
    await nextTick()
    applyOgMeta({ image: props.item.image ?? props.item.preview, type: 'article' })
  },
  { immediate: true },
)
</script>

<!-- eslint-disable vue/no-v-html -- HTML del wysiwyg propio, saneado en servidor -->
<template>
  <div class="card-single">
    <section class="single-detail">
      <!-- Preview grande (PNG del render); fallback con el nombre si no hay -->
      <div class="single-detail__preview">
        <img v-if="item.preview" class="single-detail__image" :src="item.preview" :alt="name" />
        <span v-else class="single-detail__fallback">{{ name }}</span>
      </div>

      <div class="single-detail__info">
        <InfoBlock :title="t('singles.card.basicInfo')">
          <dl class="info-list">
            <dt>{{ t('singles.card.name') }}</dt>
            <dd>{{ name }}</dd>

            <template v-if="item.faction">
              <dt>{{ t('singles.card.faction') }}</dt>
              <dd>
                <RouterLink v-if="factionRoute" class="info-link" :to="factionRoute">
                  {{ item.faction.name }}
                </RouterLink>
                <template v-else>{{ item.faction.name }}</template>
              </dd>
            </template>

            <template v-if="item.is_unique">
              <dt>{{ t('singles.card.unique') }}</dt>
              <dd>{{ t('singles.yes') }}</dd>
            </template>

            <template v-if="item.type">
              <dt>{{ t('singles.card.type') }}</dt>
              <dd>{{ item.type.name }}</dd>
            </template>

            <template v-if="item.type?.allows_subtypes && item.subtype">
              <dt>{{ t('singles.card.subtype') }}</dt>
              <dd>{{ item.subtype }}</dd>
            </template>

            <template v-if="item.type?.superclass">
              <dt>{{ t('singles.card.superclass') }}</dt>
              <dd>{{ item.type.superclass }}</dd>
            </template>

            <template v-if="item.equipment?.type">
              <dt>{{ t('singles.card.equipmentType') }}</dt>
              <dd>{{ item.equipment.type }}</dd>
            </template>

            <template v-if="item.equipment?.hands">
              <dt>{{ t('singles.card.handsLabel') }}</dt>
              <dd>
                {{ t('singles.card.hands', { count: item.equipment.hands }, item.equipment.hands) }}
              </dd>
            </template>

            <template v-if="item.cost_parsed.length">
              <dt>{{ t('singles.card.cost') }}</dt>
              <dd><DiceCost :cost="item.cost_parsed" size="sm" /></dd>
            </template>
          </dl>
        </InfoBlock>

        <InfoBlock v-if="hasAttack" :title="t('singles.card.attackInfo')">
          <dl class="info-list">
            <template v-if="item.attack.range">
              <dt>{{ t('singles.card.attackRange') }}</dt>
              <dd>{{ item.attack.range }}</dd>
            </template>

            <template v-if="item.attack.type">
              <dt>{{ t('singles.card.attackType') }}</dt>
              <dd>{{ t(`singles.attackTypes.${item.attack.type}`) }}</dd>
            </template>

            <template v-if="item.attack.subtype">
              <dt>{{ t('singles.card.attackSubtype') }}</dt>
              <dd>{{ item.attack.subtype }}</dd>
            </template>

            <template v-if="item.attack.area">
              <dt>{{ t('singles.card.area') }}</dt>
              <dd>{{ t('singles.yes') }}</dd>
            </template>
          </dl>
        </InfoBlock>

        <InfoBlock v-if="hasEffects" :title="t('singles.card.effects')">
          <div v-if="item.restriction" class="effect-section">
            <div class="effect-section__content rich-content" v-html="item.restriction" />
          </div>

          <div v-if="item.effect" class="effect-section">
            <div class="effect-section__content rich-content" v-html="item.effect" />
          </div>

          <div v-if="item.granted_ability" class="effect-section">
            <h3 class="effect-section__title">{{ t('singles.card.grantedAbility') }}</h3>
            <AbilityCard
              variant="active"
              :name="item.granted_ability.name"
              :description="item.granted_ability.description"
              :cost="item.granted_ability.cost_parsed"
              :attack="item.granted_ability.attack"
              :area="item.granted_ability.area"
            />
          </div>
        </InfoBlock>
      </div>
    </section>

    <!-- Cita épica (bloque quote del motor, centrado como el viejo) -->
    <BlockQuote v-if="quoteHtml" :settings="{ quote: quoteHtml, align: 'center' }" />

    <!-- Relateds de cartas, excluyendo la actual -->
    <CatalogRelated
      catalog-key="card"
      :exclude-id="item.id"
      :subtitle="t('singles.card.relatedSubtitle')"
      :button-label="t('singles.card.relatedButton')"
    />
  </div>
</template>
