<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { setActiveSlugMap } from '@/router'
import { ArrowLeft, SquarePen } from '@lucide/vue'
import { useResource } from '@edc-motor/admin-kit'
import { BaseButton } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { useLocalesStore } from '@/stores/locales'
import { usePageCrumb } from '@/composables/usePageCrumb'
import type { Hero, HeroAbilityRef } from '@juego/shared'
import HeroFormModal from '@/components/heroes/HeroFormModal.vue'
import InfoBlock from '@/components/InfoBlock.vue'
import PreviewPanel from '@/components/previews/PreviewPanel.vue'
import CostDice from '@/components/game/CostDice.vue'
import AttackLine from '@/components/game/AttackLine.vue'

// Single de héroe en secciones info-block (patrón del single de la app,
// versión "solo borde" del admin): información básica y atributos SIN chips
// (texto plano; la facción, coloreada con su color identitario), pasiva del
// héroe + pasiva de clase, habilidades activas con su línea
// rango-tipo-subtipo y las previews PNG con regeneración (como en cartas).
const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const locales = useLocalesStore()
const { find } = useResource<Hero>(api, '/admin/heroes')

const item = ref<Hero | null>(null)
const loading = ref(true)
const formOpen = ref(false)
const previewPanel = ref<InstanceType<typeof PreviewPanel> | null>(null)

function tr(obj: Record<string, string> | null | undefined) {
  return (
    obj?.[locales.current] || obj?.[locales.defaultLocale] || Object.values(obj || {})[0] || '—'
  )
}
const slug = computed(() => route.params.slug as string)

/** Atributos como pares etiqueta/valor para la parrilla. */
const attributes = computed(() => {
  if (!item.value) return []
  return [
    { key: 'agility', value: item.value.agility },
    { key: 'mental', value: item.value.mental },
    { key: 'will', value: item.value.will },
    { key: 'strength', value: item.value.strength },
    { key: 'armor', value: item.value.armor },
    { key: 'health', value: item.value.health },
  ]
})

/** Pasiva propia del héroe (nombre o descripción). */
const hasPassive = computed(
  () =>
    !!item.value &&
    (tr(item.value.passive_name) !== '—' || tr(item.value.passive_description) !== '—'),
)

/** Pasiva de la clase (texto de HeroClass.passive). */
const classPassive = computed(() =>
  item.value?.hero_class && tr(item.value.hero_class.passive) !== '—'
    ? tr(item.value.hero_class.passive)
    : null,
)

/** Superclase resuelta con el género (fallback al nombre neutro). */
const superclassName = computed(() => {
  if (!item.value) return '—'
  return tr(item.value.superclass_display ?? item.value.hero_class?.hero_superclass?.name)
})

/** Slug localizado de la facción embebida (enlace a su single). */
const factionSlug = computed(() => {
  const slug = item.value?.faction?.slug
  return slug?.[locales.current] || Object.values(slug ?? {})[0] || ''
})

/**
 * Enlace al index de héroes con un filtro aplicado (raza/clase/superclase):
 * mismas claves que los queryFilters del listado.
 */
function heroesFilterLink(key: string, id: number | null | undefined) {
  return { name: 'heroes', query: { [key]: String(id ?? '') } }
}

/**
 * Enlace al index de habilidades con esta habilidad seleccionada: el search
 * acota la lista (el ítem cae en la primera página) y selected lo marca.
 */
function abilityLink(ability: HeroAbilityRef) {
  return {
    name: 'hero-abilities',
    query: { selected: String(ability.id), search: tr(ability.name) },
  }
}

async function load() {
  loading.value = true
  try {
    item.value = await find(slug.value)
    setActiveSlugMap(item.value?.slug ?? null) // slug localizado al cambiar idioma
  } catch {
    item.value = null
  } finally {
    loading.value = false
  }
}
async function onSaved() {
  await load()
  previewPanel.value?.load()
}

onMounted(async () => {
  await locales.load()
  await load()
})

// El nombre del single como último tramo de la breadcrumb (se actualiza si
// cambia el locale de contenido) y fuera al salir de la vista.
const crumb = usePageCrumb()
watch(
  [item, () => locales.current],
  () => {
    if (item.value) crumb.set(tr(item.value.name))
  },
  { immediate: true },
)
onBeforeUnmount(() => {
  crumb.clear()
  setActiveSlugMap(null)
})
</script>

<!-- eslint-disable vue/no-v-html -- HTML del WYSIWYG propio (sanitización en servidor) -->
<template>
  <div v-if="item" class="single hero-single">
    <div class="single__bar">
      <BaseButton variant="text" @click="router.push({ name: 'heroes' })">
        <template #icon><ArrowLeft :size="16" /></template>
        {{ t('heroes.title') }}
      </BaseButton>
      <BaseButton variant="success" @click="formOpen = true">
        <template #icon><SquarePen :size="16" /></template>
        {{ t('common.actions.edit') }}
      </BaseButton>
    </div>

    <div class="single__layout">
      <div class="single__preview">
        <!-- Preview PNG generado si existe; si no, la ilustración -->
        <div class="hero-single__art">
          <img
            v-if="item.previews?.[locales.current]"
            :src="item.previews?.[locales.current]"
            alt=""
          />
          <img v-else-if="item.image" :src="item.image" alt="" />
          <span v-else class="hero-single__mono">{{ tr(item.name).charAt(0) }}</span>
        </div>
      </div>

      <div class="single__info">
        <h1>{{ tr(item.name) }}</h1>

        <!-- Sin chips: texto plano en el color del tema; la identidad de la
             facción va en una muestra de color al lado (el texto teñido no
             se leía con colores claros/oscuros según el tema). Las dos cards
             comparten fila mientras quepan (rejilla auto-fit). -->
        <div class="hero-single__cards">
          <InfoBlock :title="t('heroes.sections.basic')">
            <dl class="info-list">
              <dt>{{ t('heroes.fields.faction') }}</dt>
              <dd>
                <!-- Enlace discreto al single de la facción -->
                <RouterLink
                  v-if="item.faction && factionSlug"
                  class="hero-link"
                  :to="{ name: 'faction-single', params: { slug: factionSlug } }"
                >
                  <span
                    v-if="item.faction.color"
                    class="swatch"
                    :style="{ background: item.faction.color }"
                  />{{ tr(item.faction.name) }}
                </RouterLink>
                <template v-else>
                  <span
                    v-if="item.faction?.color"
                    class="swatch"
                    :style="{ background: item.faction.color }"
                  />{{ item.faction ? tr(item.faction.name) : t('heroes.fields.noFaction') }}
                </template>
              </dd>

              <!-- Raza, clase y superclase con el género del héroe (·_display);
                   enlaces discretos al index de héroes con ese filtro aplicado -->
              <template v-if="item.hero_race">
                <dt>{{ t('heroes.fields.race') }}</dt>
                <dd>
                  <RouterLink
                    class="hero-link"
                    :to="heroesFilterLink('hero_race_id', item.hero_race_id)"
                    >{{ tr(item.race_display ?? item.hero_race.name) }}</RouterLink
                  >
                </dd>
              </template>

              <template v-if="item.hero_class">
                <dt>{{ t('heroes.fields.class') }}</dt>
                <dd>
                  <RouterLink
                    class="hero-link"
                    :to="heroesFilterLink('hero_class_id', item.hero_class_id)"
                    >{{ tr(item.class_display ?? item.hero_class.name) }}</RouterLink
                  >
                </dd>
              </template>

              <template v-if="superclassName !== '—'">
                <dt>{{ t('heroes.fields.superclass') }}</dt>
                <dd>
                  <RouterLink
                    v-if="item.hero_class?.hero_superclass_id"
                    class="hero-link"
                    :to="heroesFilterLink('hero_superclass_id', item.hero_class.hero_superclass_id)"
                    >{{ superclassName }}</RouterLink
                  >
                  <template v-else>{{ superclassName }}</template>
                </dd>
              </template>

              <dt>{{ t('heroes.fields.gender') }}</dt>
              <dd>{{ t(`heroes.genders.${item.gender}`) }}</dd>
            </dl>
          </InfoBlock>

          <!-- Atributos en el mismo formato clave→valor que Información básica -->
          <InfoBlock :title="t('heroes.sections.attributes')">
            <dl class="info-list">
              <template v-for="attribute in attributes" :key="attribute.key">
                <dt>{{ t(`heroes.attributes.${attribute.key}`) }}</dt>
                <dd>{{ attribute.value }}</dd>
              </template>
              <dt>{{ t('heroes.fields.totalAttributes') }}</dt>
              <dd>{{ item.total_attributes }}</dd>
            </dl>
          </InfoBlock>
        </div>
      </div>
    </div>

    <!-- Pasiva formateada "nombre: texto" (nombre en negrita, texto en línea) -->
    <InfoBlock v-if="hasPassive" :title="t('heroes.sections.passive')">
      <div class="hero-single__passive-line">
        <strong v-if="tr(item.passive_name) !== '—'">{{ tr(item.passive_name) }}:</strong>
        <span class="rich-content" v-html="tr(item.passive_description)" />
      </div>
    </InfoBlock>

    <!-- Pasiva de la clase (nombre = clase con el género del héroe) -->
    <InfoBlock v-if="classPassive" :title="t('heroes.sections.classPassive')">
      <div class="hero-single__passive-line">
        <strong v-if="item.hero_class"
          >{{ tr(item.class_display ?? item.hero_class.name) }}:</strong
        >
        <span class="rich-content" v-html="classPassive" />
      </div>
    </InfoBlock>

    <InfoBlock :title="t('heroes.sections.abilities')">
      <p v-if="!item.abilities || !item.abilities.length" class="hero-single__empty">
        {{ t('heroes.fields.noAbilities') }}
      </p>
      <ol v-else class="hero-single__abilities">
        <li v-for="ability in item.abilities" :key="ability.id">
          <p class="hero-single__ability-head">
            <!-- Enlace discreto al index de habilidades con esta seleccionada -->
            <strong
              ><RouterLink class="hero-link" :to="abilityLink(ability)">{{
                tr(ability.name)
              }}</RouterLink></strong
            >
            <CostDice v-if="ability.cost" :cost="ability.cost" />
            <!-- Línea de ataque, SIEMPRE rango-tipo-subtipo (texto coloreado) -->
            <AttackLine
              :range="ability.attack_range"
              :type="ability.attack_type"
              :subtype="ability.attack_subtype"
              :area="ability.area"
            />
          </p>
          <div class="rich-content" v-html="tr(ability.description)" />
        </li>
      </ol>
    </InfoBlock>

    <template v-if="tr(item.lore_text) !== '—' || tr(item.epic_quote) !== '—'">
      <h2 class="single__section">{{ t('heroes.sections.lore') }}</h2>
      <div class="rich-content" v-html="tr(item.lore_text)" />
      <blockquote
        v-if="tr(item.epic_quote) !== '—'"
        class="hero-single__quote rich-content"
        v-html="tr(item.epic_quote)"
      />
    </template>

    <!-- PNG generados por locale, con regeneración en cola (como en cartas) -->
    <PreviewPanel :id="item.id" ref="previewPanel" entity="hero" />

    <HeroFormModal v-model="formOpen" mode="edit" :target-slug="slug" @saved="onSaved" />
  </div>
  <p v-else-if="!loading" class="single__empty">{{ t('common.empty') }}</p>
</template>
