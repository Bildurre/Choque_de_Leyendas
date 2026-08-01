<script setup lang="ts">
import { computed, nextTick, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { BlockQuote, BlockShell } from '@edc-motor/ui'
import AbilityCard, { type AbilityAttack } from '@/components/singles/AbilityCard.vue'
import CatalogRelated from '@/components/singles/CatalogRelated.vue'
import type { CostDie } from '@/components/singles/DiceCost.vue'
import InfoBlock from '@/components/singles/InfoBlock.vue'
import { applyOgMeta } from '@/entities/singleOg'
import { sectionDetailRoute, sectionIndexRoute } from '@/entities/singleRoutes'

// Single de héroe (portado de public/heroes/show.blade.php del viejo), ya en
// el LENGUAJE DE BLOQUES del CRM (blockHeader del registry): lo monta
// EntityDetailView, que pinta el header-bloque (título = nombre, tinte del
// color de la facción, volver + añadir dentro) y el fondo/head SEO; aquí
// cada sección es un `block` a lo ancho: ficha en fila (preview |
// información básica | atributos, con cortes explícitos de container
// query), habilidades (pasivas de clase y propia + activas con dados de
// coste), lore, cita épica (bloque quote del CRM, tinte gris) y relateds de
// héroes ALEATORIOS (bloque related del CRM). Los atributos van sin icono:
// la API pública no expone los iconos del juego (desviación anotada).
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
  race_id: number | null
  gender: string | null
  class: string | null
  class_id: number | null
  superclass: string | null
  superclass_id: number | null
  attributes: { agility: number; mental: number; will: number; strength: number; armor: number }
  health: number
  class_passive: Passive | null
  passive: Passive | null
  abilities: HeroAbility[]
  lore_text: string
  epic_quote: string
}

const props = defineProps<{ item: HeroPayload; locale: string }>()

const { t } = useI18n()

// Orden de la lista del viejo: agilidad, mente, voluntad, fuerza,
// armadura y la salud derivada al final.
const ATTRIBUTE_KEYS = ['agility', 'mental', 'will', 'strength', 'armor'] as const

const name = computed(
  () => props.item.name[props.locale] || Object.values(props.item.name)[0] || '',
)

const factionRoute = computed(() =>
  props.item.faction ? sectionDetailRoute('factions', props.item.faction.slug, props.locale) : null,
)

// Raza / clase / superclase enlazan al ÍNDICE de héroes FILTRADO por su id:
// las claves de query (race/class/superclass) son las que lee el
// HeroesCatalog vía useFiltersQuery, así el índice aterriza con el filtro
// aplicado y su chip. Género y nombre no enlazan (no son filtros).
const indexFilterRoute = (key: string, id: number | null) =>
  id != null ? sectionIndexRoute('heroes', props.locale, { [key]: String(id) }) : null

const raceRoute = computed(() => indexFilterRoute('race', props.item.race_id))
const classRoute = computed(() => indexFilterRoute('class', props.item.class_id))
const superclassRoute = computed(() => indexFilterRoute('superclass', props.item.superclass_id))

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

<!-- eslint-disable vue/no-v-html -- HTML del wysiwyg propio, saneado en servidor -->
<template>
  <div class="hero-single single-sections">
    <!-- Ficha: preview | información básica | atributos, EN FILA mientras
         quepan (columnas fijas, cortes explícitos en _singles.scss) -->
    <BlockShell :settings="{ width: 'wide', align: 'left' }">
      <div class="single-detail single-detail--hero">
        <!-- Preview grande (PNG del render); fallback con el nombre si no hay -->
        <div class="single-detail__preview">
          <img v-if="item.preview" class="single-detail__image" :src="item.preview" :alt="name" />
          <span v-else class="single-detail__fallback">{{ name }}</span>
        </div>

        <InfoBlock class="single-detail__basic" :title="t('singles.hero.basicInfo')">
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
              <dd>
                <RouterLink v-if="raceRoute" class="info-link" :to="raceRoute">
                  {{ item.race }}
                </RouterLink>
                <template v-else>{{ item.race }}</template>
              </dd>
            </template>

            <template v-if="item.gender">
              <dt>{{ t('singles.hero.gender') }}</dt>
              <dd>{{ t(`singles.hero.genders.${item.gender}`) }}</dd>
            </template>

            <template v-if="item.class">
              <dt>{{ t('singles.hero.class') }}</dt>
              <dd>
                <RouterLink v-if="classRoute" class="info-link" :to="classRoute">
                  {{ item.class }}
                </RouterLink>
                <template v-else>{{ item.class }}</template>
              </dd>
            </template>

            <template v-if="item.superclass">
              <dt>{{ t('singles.hero.superclass') }}</dt>
              <dd>
                <RouterLink v-if="superclassRoute" class="info-link" :to="superclassRoute">
                  {{ item.superclass }}
                </RouterLink>
                <template v-else>{{ item.superclass }}</template>
              </dd>
            </template>
          </dl>
        </InfoBlock>

        <!-- Atributos con el MISMO formato label→value que la card de
             Información (dl .info-list, sin alinear el valor a la derecha);
             la salud derivada cierra la lista, sin acento -->
        <InfoBlock class="single-detail__attrs" :title="t('singles.hero.attributes')">
          <dl class="info-list">
            <template v-for="key in ATTRIBUTE_KEYS" :key="key">
              <dt>{{ t(`singles.hero.attribute.${key}`) }}</dt>
              <dd>{{ item.attributes[key] }}</dd>
            </template>

            <dt>{{ t('singles.hero.attribute.health') }}</dt>
            <dd>{{ item.health }}</dd>
          </dl>
        </InfoBlock>
      </div>
    </BlockShell>

    <!-- Habilidades (contenido intacto), al ancho wide de bloque -->
    <BlockShell
      v-if="hasPassives || item.abilities.length"
      :settings="{ width: 'wide', align: 'left' }"
    >
      <InfoBlock :title="t('singles.hero.abilities')">
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
    </BlockShell>

    <!-- Lore del héroe (wysiwyg): sección rich-content a lo ancho — con el
         header nuevo ya no hay subtítulo en el banner, el lore vive aquí -->
    <BlockShell v-if="item.lore_text" :settings="{ width: 'wide', align: 'left' }">
      <h2 class="block__title">{{ t('singles.loreTitle') }}</h2>
      <div class="single-lore rich-content" v-html="item.lore_text" />
    </BlockShell>

    <!-- Cita épica: EXACTA al bloque quote del CRM (wide, centrada, tinte
         gris por defecto del CRM al 15%) -->
    <BlockQuote
      v-if="quoteHtml"
      :settings="{ quote: quoteHtml, align: 'center', width: 'wide', background: '#64748B' }"
    />

    <!-- Relateds de héroes ALEATORIOS (bloque related del CRM), excluyendo
         el actual -->
    <CatalogRelated
      catalog-key="hero"
      :exclude-id="item.id"
      :title="t('singles.hero.relatedTitle')"
      :button-label="t('singles.hero.relatedButton')"
    />
  </div>
</template>
