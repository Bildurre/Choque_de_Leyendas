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

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const locales = useLocalesStore()
const { find } = useResource<Hero>(api, '/admin/heroes')

const item = ref<Hero | null>(null)
const loading = ref(true)
const formOpen = ref(false)

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
        <p class="single__meta">
          <span class="chip">{{
            item.faction ? tr(item.faction.name) : t('heroes.fields.noFaction')
          }}</span>
          <span v-if="item.hero_race" class="chip">{{ tr(item.hero_race.name) }}</span>
          <span v-if="item.hero_class" class="chip">{{ tr(item.hero_class.name) }}</span>
          <span class="chip">{{ t(`heroes.genders.${item.gender}`) }}</span>
        </p>

        <ul class="hero-single__attributes">
          <li v-for="attribute in attributes" :key="attribute.key">
            <strong>{{ t(`heroes.attributes.${attribute.key}`) }}</strong>
            <span>{{ attribute.value }}</span>
          </li>
        </ul>
        <p class="hero-single__total">
          {{ t('heroes.fields.totalAttributes') }}: {{ item.total_attributes }}
        </p>

        <template v-if="tr(item.passive_name) !== '—' || tr(item.passive_description) !== '—'">
          <h2 class="single__section">{{ t('heroes.sections.passive') }}</h2>
          <h3 v-if="tr(item.passive_name) !== '—'" class="hero-single__passive-name">
            {{ tr(item.passive_name) }}
          </h3>
          <div class="rich-content" v-html="tr(item.passive_description)" />
        </template>
      </div>
    </div>

    <h2 class="single__section">{{ t('heroes.sections.abilities') }}</h2>
    <p v-if="!item.abilities || !item.abilities.length" class="hero-single__empty">
      {{ t('heroes.fields.noAbilities') }}
    </p>
    <ol v-else class="hero-single__abilities">
      <li v-for="ability in item.abilities" :key="ability.id">
        <p class="hero-single__ability-head">
          <strong>{{ tr(ability.name) }}</strong>
          <code v-if="ability.cost" class="hero-single__ability-cost">{{ ability.cost }}</code>
          <span v-if="ability.attack_type" class="chip">{{
            t(`heroAbilities.attackTypes.${ability.attack_type}`)
          }}</span>
          <span v-if="ability.area" class="chip">{{ t('heroAbilities.fields.area') }}</span>
        </p>
        <div class="rich-content" v-html="tr(ability.description)" />
      </li>
    </ol>

    <template v-if="tr(item.lore_text) !== '—' || tr(item.epic_quote) !== '—'">
      <h2 class="single__section">{{ t('heroes.sections.lore') }}</h2>
      <div class="rich-content" v-html="tr(item.lore_text)" />
      <blockquote
        v-if="tr(item.epic_quote) !== '—'"
        class="hero-single__quote rich-content"
        v-html="tr(item.epic_quote)"
      />
    </template>

    <HeroFormModal v-model="formOpen" mode="edit" :target-slug="slug" @saved="onSaved" />
  </div>
  <p v-else-if="!loading" class="single__empty">{{ t('common.empty') }}</p>
</template>
