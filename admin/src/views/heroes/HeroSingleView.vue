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
import type { Hero } from '@juego/shared'
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

        <!-- Sin chips: texto plano, la facción coloreada con su color -->
        <InfoBlock :title="t('heroes.sections.basic')">
          <dl class="info-list">
            <dt>{{ t('heroes.fields.faction') }}</dt>
            <dd :style="item.faction?.color ? { color: item.faction.color } : undefined">
              {{ item.faction ? tr(item.faction.name) : t('heroes.fields.noFaction') }}
            </dd>

            <!-- Raza, clase y superclase con el género del héroe (·_display) -->
            <template v-if="item.hero_race">
              <dt>{{ t('heroes.fields.race') }}</dt>
              <dd>{{ tr(item.race_display ?? item.hero_race.name) }}</dd>
            </template>

            <template v-if="item.hero_class">
              <dt>{{ t('heroes.fields.class') }}</dt>
              <dd>{{ tr(item.class_display ?? item.hero_class.name) }}</dd>
            </template>

            <template v-if="superclassName !== '—'">
              <dt>{{ t('heroes.fields.superclass') }}</dt>
              <dd>{{ superclassName }}</dd>
            </template>

            <dt>{{ t('heroes.fields.gender') }}</dt>
            <dd>{{ t(`heroes.genders.${item.gender}`) }}</dd>
          </dl>
        </InfoBlock>

        <InfoBlock :title="t('heroes.sections.attributes')">
          <ul class="info-attributes">
            <li v-for="attribute in attributes" :key="attribute.key">
              <strong>{{ t(`heroes.attributes.${attribute.key}`) }}</strong>
              <span>{{ attribute.value }}</span>
            </li>
          </ul>
          <p class="hero-single__total">
            {{ t('heroes.fields.totalAttributes') }}: {{ item.total_attributes }}
          </p>
        </InfoBlock>
      </div>
    </div>

    <InfoBlock v-if="hasPassive" :title="t('heroes.sections.passive')">
      <h3 v-if="tr(item.passive_name) !== '—'" class="hero-single__passive-name">
        {{ tr(item.passive_name) }}
      </h3>
      <div class="rich-content" v-html="tr(item.passive_description)" />
    </InfoBlock>

    <!-- Pasiva de la clase (nombre = clase con el género del héroe) -->
    <InfoBlock v-if="classPassive" :title="t('heroes.sections.classPassive')">
      <h3 v-if="item.hero_class" class="hero-single__passive-name">
        {{ tr(item.class_display ?? item.hero_class.name) }}
      </h3>
      <div class="rich-content" v-html="classPassive" />
    </InfoBlock>

    <InfoBlock :title="t('heroes.sections.abilities')">
      <p v-if="!item.abilities || !item.abilities.length" class="hero-single__empty">
        {{ t('heroes.fields.noAbilities') }}
      </p>
      <ol v-else class="hero-single__abilities">
        <li v-for="ability in item.abilities" :key="ability.id">
          <p class="hero-single__ability-head">
            <strong>{{ tr(ability.name) }}</strong>
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
