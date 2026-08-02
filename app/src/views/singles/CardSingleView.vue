<script setup lang="ts">
import { computed, nextTick, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { BlockQuote, BlockShell } from '@edc-motor/ui'
import AbilityCard, { type AbilityAttack } from '@/components/singles/AbilityCard.vue'
import CatalogRelated from '@/components/singles/CatalogRelated.vue'
import DiceCost, { type CostDie } from '@/components/singles/DiceCost.vue'
import InfoBlock from '@/components/singles/InfoBlock.vue'
import { applyOgMeta } from '@/entities/singleOg'
import { sectionDetailRoute, sectionIndexRoute } from '@/entities/singleRoutes'

// Single de carta (portado de public/cards/show.blade.php del viejo), ya en
// el LENGUAJE DE BLOQUES del CRM (blockHeader del registry): lo monta
// EntityDetailView, que pinta el header-bloque (título = nombre, tinte del
// color de la facción, volver + acciones dentro) y el fondo/head SEO; aquí
// cada sección es un `block` a lo ancho: ficha en grid (preview a la
// izquierda; a su derecha ARRIBA la tarjeta SIN título con el trasfondo
// rich del wysiwyg a las dos columnas, y debajo detalles | ataque, con
// cortes explícitos de container query), efectos DEBAJO a todo el ancho,
// cita épica (bloque quote del CRM, fondo DINÁMICO del tema
// token:surface-3, OPACO) y relateds de cartas ALEATORIAS (bloque related del CRM). Los
// values con filtro en el catálogo de cartas enlazan al índice FILTRADO
// (query params de useFiltersQuery, patrón del viejo recuperado); la
// superclase del tipo enlaza al índice de HÉROES filtrado por ella.
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
    id: number
    name: string
    superclass: string | null
    superclass_id: number | null
    allows_subtypes: boolean
    is_equipment: boolean
  } | null
  subtype: string | null
  subtype_id: number | null
  equipment: {
    type: string | null
    type_id: number | null
    subtype: string | null
    subtype_id: number | null
    hands: number | null
  } | null
  cost: string | null
  cost_parsed: CostDie[]
  is_unique: boolean
  attack: {
    type: string | null
    range: string | null
    range_id: number | null
    subtype: string | null
    subtype_id: number | null
    area: boolean
  }
  effect: string
  restriction: string
  granted_ability: GrantedAbility | null
  lore_text: string
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

// Los values de detalles y ataque enlazan al ÍNDICE de cartas FILTRADO: las
// claves de query (type/subtype/equip/esub/range/atk/asub/area) son las que
// lee el CardsCatalog vía useFiltersQuery, así el índice aterriza con el
// filtro aplicado y su chip. Los filtros condicionales del catálogo (equipo
// y ataque solo salen con un tipo que los permita marcado) viajan SIEMPRE
// acompañados del tipo de la carta: sin él, el saneo del catálogo los
// limpiaría al aterrizar. Sin filtro correspondiente no hay enlace: manos,
// coste (gráfico de dados), única y la habilidad otorgada (no hay índice
// público de habilidades). La superclase del tipo es la excepción: no hay
// filtro en el catálogo de CARTAS, así que enlaza al índice de HÉROES
// filtrado por esa superclase (query `superclass`, la misma clave que usan
// los enlaces del single de héroe).
const cardsIndexRoute = (query: Record<string, string>) =>
  sectionIndexRoute('cards', props.locale, query)

const typeId = computed(() => props.item.type?.id ?? null)

const typeRoute = computed(() =>
  typeId.value != null ? cardsIndexRoute({ type: String(typeId.value) }) : null,
)

const subtypeRoute = computed(() =>
  props.item.subtype_id != null
    ? cardsIndexRoute({ subtype: String(props.item.subtype_id) })
    : null,
)

// La superclase del tipo → índice de héroes filtrado por ella.
const superclassRoute = computed(() => {
  const id = props.item.type?.superclass_id
  return id != null ? sectionIndexRoute('heroes', props.locale, { superclass: String(id) }) : null
})

/** Enlace a un filtro condicional (equipo/ataque): siempre con el tipo. */
const conditionalRoute = (key: string, value: string | number | null) =>
  typeId.value != null && value != null
    ? cardsIndexRoute({ type: String(typeId.value), [key]: String(value) })
    : null

const equipmentTypeRoute = computed(() =>
  conditionalRoute('equip', props.item.equipment?.type_id ?? null),
)
const equipmentSubtypeRoute = computed(() =>
  conditionalRoute('esub', props.item.equipment?.subtype_id ?? null),
)
const attackRangeRoute = computed(() => conditionalRoute('range', props.item.attack.range_id))
const attackTypeRoute = computed(() => conditionalRoute('atk', props.item.attack.type))
const attackSubtypeRoute = computed(() => conditionalRoute('asub', props.item.attack.subtype_id))
const areaRoute = computed(() => conditionalRoute('area', props.item.attack.area ? '1' : null))

const hasAttack = computed(() => {
  const attack = props.item.attack
  return Boolean(attack?.range || attack?.type || attack?.subtype || attack?.area)
})

const hasEffects = computed(() =>
  Boolean(props.item.restriction || props.item.effect || props.item.granted_ability),
)

// Trasfondo del wysiwyg TAL CUAL (rich, saneado en servidor): tarjeta sin
// título arriba a la derecha de la preview.
const loreHtml = computed(() => props.item.lore_text?.trim() ?? '')

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
  <div class="card-single single-sections">
    <!-- Ficha en grid (columnas fijas, cortes explícitos en _singles.scss):
         preview a la izquierda; a su derecha el trasfondo (tarjeta sin
         título, dos columnas) y debajo detalles | ataque; los efectos bajan
         a su propio bloque -->

    <BlockShell :settings="{ width: 'wide', align: 'left' }">
      <div
        class="single-detail single-detail--card"
        :class="{ 'single-detail--with-lore': loreHtml }"
      >
        <!-- Preview grande (PNG del render); fallback con el nombre si no hay -->
        <div class="single-detail__preview">
          <img v-if="item.preview" class="single-detail__image" :src="item.preview" :alt="name" />
          <span v-else class="single-detail__fallback">{{ name }}</span>
        </div>

        <!-- Trasfondo: tarjeta SIN título con el rich del wysiwyg, arriba a
             la derecha de la imagen ocupando las dos columnas -->
        <InfoBlock v-if="loreHtml" class="single-detail__lore">
          <div class="rich-content" v-html="loreHtml" />
        </InfoBlock>

        <InfoBlock class="single-detail__basic" :title="t('singles.card.basicInfo')">
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
              <dd>
                <RouterLink v-if="typeRoute" class="info-link" :to="typeRoute">
                  {{ item.type.name }}
                </RouterLink>
                <template v-else>{{ item.type.name }}</template>
              </dd>
            </template>

            <template v-if="item.type?.allows_subtypes && item.subtype">
              <dt>{{ t('singles.card.subtype') }}</dt>
              <dd>
                <RouterLink v-if="subtypeRoute" class="info-link" :to="subtypeRoute">
                  {{ item.subtype }}
                </RouterLink>
                <template v-else>{{ item.subtype }}</template>
              </dd>
            </template>

            <!-- La superclase del tipo enlaza al índice de HÉROES filtrado
                 por ella (el catálogo de cartas no tiene ese filtro) -->
            <template v-if="item.type?.superclass">
              <dt>{{ t('singles.card.superclass') }}</dt>
              <dd>
                <RouterLink v-if="superclassRoute" class="info-link" :to="superclassRoute">
                  {{ item.type.superclass }}
                </RouterLink>
                <template v-else>{{ item.type.superclass }}</template>
              </dd>
            </template>

            <template v-if="item.equipment?.type">
              <dt>{{ t('singles.card.equipmentType') }}</dt>
              <dd>
                <RouterLink v-if="equipmentTypeRoute" class="info-link" :to="equipmentTypeRoute">
                  {{ item.equipment.type }}
                </RouterLink>
                <template v-else>{{ item.equipment.type }}</template>
              </dd>
            </template>

            <template v-if="item.equipment?.subtype">
              <dt>{{ t('singles.card.equipmentSubtype') }}</dt>
              <dd>
                <RouterLink
                  v-if="equipmentSubtypeRoute"
                  class="info-link"
                  :to="equipmentSubtypeRoute"
                >
                  {{ item.equipment.subtype }}
                </RouterLink>
                <template v-else>{{ item.equipment.subtype }}</template>
              </dd>
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

        <InfoBlock
          v-if="hasAttack"
          class="single-detail__attack"
          :title="t('singles.card.attackInfo')"
        >
          <dl class="info-list">
            <template v-if="item.attack.range">
              <dt>{{ t('singles.card.attackRange') }}</dt>
              <dd>
                <RouterLink v-if="attackRangeRoute" class="info-link" :to="attackRangeRoute">
                  {{ item.attack.range }}
                </RouterLink>
                <template v-else>{{ item.attack.range }}</template>
              </dd>
            </template>

            <template v-if="item.attack.type">
              <dt>{{ t('singles.card.attackType') }}</dt>
              <dd>
                <RouterLink v-if="attackTypeRoute" class="info-link" :to="attackTypeRoute">
                  {{ t(`singles.attackTypes.${item.attack.type}`) }}
                </RouterLink>
                <template v-else>{{ t(`singles.attackTypes.${item.attack.type}`) }}</template>
              </dd>
            </template>

            <template v-if="item.attack.subtype">
              <dt>{{ t('singles.card.attackSubtype') }}</dt>
              <dd>
                <RouterLink v-if="attackSubtypeRoute" class="info-link" :to="attackSubtypeRoute">
                  {{ item.attack.subtype }}
                </RouterLink>
                <template v-else>{{ item.attack.subtype }}</template>
              </dd>
            </template>

            <template v-if="item.attack.area">
              <dt>{{ t('singles.card.area') }}</dt>
              <dd>
                <RouterLink v-if="areaRoute" class="info-link" :to="areaRoute">
                  {{ t('singles.yes') }}
                </RouterLink>
                <template v-else>{{ t('singles.yes') }}</template>
              </dd>
            </template>
          </dl>
        </InfoBlock>
      </div>
    </BlockShell>

    <!-- Efectos DEBAJO de la ficha, a todo el ancho (como las habilidades
         en el single de héroe): a la derecha de la imagen quedan solo
         detalles y ataque -->
    <BlockShell v-if="hasEffects" :settings="{ width: 'wide', align: 'left' }">
      <InfoBlock :title="t('singles.card.effects')">
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
    </BlockShell>

    <!-- Cita épica: EXACTA al bloque quote del CRM (wide, centrada, fondo
         DINÁMICO Y OPACO del tema: token:surface-3 resuelto por BlockShell;
         surface pelado no contrastaba con la página en claro) -->
    <BlockQuote
      v-if="quoteHtml"
      :settings="{
        quote: quoteHtml,
        align: 'center',
        width: 'wide',
        background: 'token:surface-3',
      }"
    />

    <!-- Relateds de cartas ALEATORIAS (bloque related del CRM), excluyendo
         la actual -->
    <CatalogRelated
      catalog-key="card"
      :exclude-id="item.id"
      :title="t('singles.card.relatedTitle')"
      :button-label="t('singles.card.relatedButton')"
    />
  </div>
</template>
