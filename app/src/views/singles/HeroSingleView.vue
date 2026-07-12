<script setup lang="ts">
import { computed, nextTick, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { BlockQuote } from '@edc-motor/ui'
import AbilityCard, { type AbilityAttack } from '@/components/singles/AbilityCard.vue'
import CatalogRelated from '@/components/singles/CatalogRelated.vue'
import type { CostDie } from '@/components/singles/DiceCost.vue'
import InfoBlock from '@/components/singles/InfoBlock.vue'
import { applyOgMeta } from '@/entities/singleOg'
import { sectionDetailRoute } from '@/entities/singleRoutes'

// Single de héroe (portado de public/heroes/show.blade.php del viejo): PNG
// grande + "Información del Héroe", rejilla de atributos (+salud derivada),
// habilidades (pasivas de clase y propia + activas con dados de coste),
// cita épica y relateds de héroes. Lo monta EntityDetailView (banner, fondo,
// añadir a la colección y head SEO). Los atributos van sin icono: la API
// pública no expone los iconos del juego (desviación anotada).
interface FactionRef {
  id: number
  name: string
  slug: string | null
  color: string
  text_is_dark: boolean
}

interface HeroAbility {
  id: number
  name: string
  description: string
  cost_parsed: CostDie[]
  attack: AbilityAttack
  area: boolean
}

interface Passive {
  name: string
  description: string
}

interface HeroPayload {
  id: number
  name: Record<string, string>
  slug: Record<string, string>
  image: string | null
  preview: string | null
  faction: FactionRef | null
  race: string | null
  gender: string | null
  class: string | null
  superclass: string | null
  attributes: { agility: number; mental: number; will: number; strength: number; armor: number }
  health: number
  class_passive: Passive | null
  passive: Passive | null
  abilities: HeroAbility[]
  epic_quote: string
}

const props = defineProps<{ item: HeroPayload; locale: string }>()

const { t } = useI18n()

// Orden de la rejilla del viejo: agilidad, mente, voluntad, fuerza,
// armadura y la salud derivada al final.
const ATTRIBUTE_KEYS = ['agility', 'mental', 'will', 'strength', 'armor'] as const

const name = computed(
  () => props.item.name[props.locale] || Object.values(props.item.name)[0] || '',
)

const factionRoute = computed(() =>
  props.item.faction ? sectionDetailRoute('factions', props.item.faction.slug, props.locale) : null,
)

const hasPassives = computed(() => Boolean(props.item.class_passive || props.item.passive))

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

<template>
  <div class="hero-single">
    <section class="single-detail">
      <!-- Preview grande (PNG del render); fallback con el nombre si no hay -->
      <div class="single-detail__preview">
        <img v-if="item.preview" class="single-detail__image" :src="item.preview" :alt="name" />
        <span v-else class="single-detail__fallback">{{ name }}</span>
      </div>

      <div class="single-detail__info">
        <InfoBlock :title="t('singles.hero.basicInfo')">
          <dl class="info-list">
            <dt>{{ t('singles.hero.name') }}</dt>
            <dd>{{ name }}</dd>

            <template v-if="item.faction">
              <dt>{{ t('singles.hero.faction') }}</dt>
              <dd>
                <RouterLink v-if="factionRoute" class="info-link" :to="factionRoute">
                  {{ item.faction.name }}
                </RouterLink>
                <template v-else>{{ item.faction.name }}</template>
              </dd>
            </template>

            <template v-if="item.race">
              <dt>{{ t('singles.hero.race') }}</dt>
              <dd>{{ item.race }}</dd>
            </template>

            <template v-if="item.gender">
              <dt>{{ t('singles.hero.gender') }}</dt>
              <dd>{{ t(`singles.hero.genders.${item.gender}`) }}</dd>
            </template>

            <template v-if="item.class">
              <dt>{{ t('singles.hero.class') }}</dt>
              <dd>{{ item.class }}</dd>
            </template>

            <template v-if="item.superclass">
              <dt>{{ t('singles.hero.superclass') }}</dt>
              <dd>{{ item.superclass }}</dd>
            </template>
          </dl>
        </InfoBlock>

        <InfoBlock :title="t('singles.hero.attributes')">
          <div class="attributes-grid">
            <div v-for="key in ATTRIBUTE_KEYS" :key="key" class="attribute-item">
              <span class="attribute-item__label">{{ t(`singles.hero.attribute.${key}`) }}</span>
              <span class="attribute-item__value">{{ item.attributes[key] }}</span>
            </div>
            <div class="attribute-item attribute-item--health">
              <span class="attribute-item__label">{{ t('singles.hero.attribute.health') }}</span>
              <span class="attribute-item__value">{{ item.health }}</span>
            </div>
          </div>
        </InfoBlock>
      </div>

      <!-- Habilidades a todo el ancho (info-block--abilities del viejo) -->
      <InfoBlock
        v-if="hasPassives || item.abilities.length"
        class="info-block--abilities"
        :title="t('singles.hero.abilities')"
      >
        <div v-if="hasPassives" class="abilities-section">
          <h3 class="abilities-section__subtitle">{{ t('singles.hero.passiveAbilities') }}</h3>

          <AbilityCard
            v-if="item.class_passive"
            variant="passive"
            :name="item.class_passive.name"
            :description="item.class_passive.description"
          />
          <AbilityCard
            v-if="item.passive"
            variant="passive"
            :name="item.passive.name"
            :description="item.passive.description"
          />
        </div>

        <div v-if="item.abilities.length" class="abilities-section">
          <h3 class="abilities-section__subtitle">{{ t('singles.hero.activeAbilities') }}</h3>

          <AbilityCard
            v-for="ability in item.abilities"
            :key="ability.id"
            variant="active"
            :name="ability.name"
            :description="ability.description"
            :cost="ability.cost_parsed"
            :attack="ability.attack"
            :area="ability.area"
          />
        </div>
      </InfoBlock>
    </section>

    <!-- Cita épica (bloque quote del motor, centrado como el viejo) -->
    <BlockQuote v-if="quoteHtml" :settings="{ quote: quoteHtml, align: 'center' }" />

    <!-- Relateds de héroes, excluyendo el actual -->
    <CatalogRelated
      catalog-key="hero"
      :exclude-id="item.id"
      :subtitle="t('singles.hero.relatedSubtitle')"
      :button-label="t('singles.hero.relatedButton')"
    />
  </div>
</template>
