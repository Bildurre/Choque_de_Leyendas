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
import type { Card } from '@juego/shared'
import CardFormModal from '@/components/cards/CardFormModal.vue'
import PreviewPanel from '@/components/previews/PreviewPanel.vue'
import CostDice from '@/components/game/CostDice.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const locales = useLocalesStore()
const { find } = useResource<Card>(api, '/admin/cards')

const item = ref<Card | null>(null)
const loading = ref(true)
const formOpen = ref(false)
const previewPanel = ref<InstanceType<typeof PreviewPanel> | null>(null)

function tr(obj: Record<string, string> | null | undefined) {
  return (
    obj?.[locales.current] || obj?.[locales.defaultLocale] || Object.values(obj || {})[0] || '—'
  )
}
const slug = computed(() => route.params.slug as string)

/** Datos de ataque como chips, en orden canónico rango → tipo → subtipo. */
const attackChips = computed(() => {
  if (!item.value) return []
  const chips: string[] = []
  if (item.value.attack_range) chips.push(tr(item.value.attack_range.name))
  if (item.value.attack_type) chips.push(t(`cards.attackTypes.${item.value.attack_type}`))
  if (item.value.attack_subtype) chips.push(tr(item.value.attack_subtype.name))
  if (item.value.area) chips.push(t('cards.fields.area'))
  return chips
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
  <div v-if="item" class="single card-single">
    <div class="single__bar">
      <BaseButton variant="text" @click="router.push({ name: 'cards' })">
        <template #icon><ArrowLeft :size="16" /></template>
        {{ t('cards.title') }}
      </BaseButton>
      <BaseButton variant="success" @click="formOpen = true">
        <template #icon><SquarePen :size="16" /></template>
        {{ t('common.actions.edit') }}
      </BaseButton>
    </div>

    <div class="single__layout">
      <div class="single__preview">
        <!-- Preview PNG generado si existe; si no, la ilustración -->
        <div class="card-single__art">
          <img
            v-if="item.previews?.[locales.current]"
            :src="item.previews[locales.current]"
            alt=""
          />
          <img v-else-if="item.image" :src="item.image" alt="" />
          <span v-else class="card-single__mono">{{ tr(item.name).charAt(0) }}</span>
        </div>
      </div>

      <div class="single__info">
        <h1>{{ tr(item.name) }}</h1>
        <p class="single__meta">
          <span class="chip">{{
            item.faction ? tr(item.faction.name) : t('cards.fields.noFaction')
          }}</span>
          <span v-if="item.card_type" class="chip">{{ tr(item.card_type.name) }}</span>
          <span v-if="item.card_subtype" class="chip">{{ tr(item.card_subtype.name) }}</span>
          <span v-if="item.equipment_type" class="chip">{{ tr(item.equipment_type.name) }}</span>
          <span v-if="item.is_unique" class="chip">{{ t('cards.fields.isUnique') }}</span>
        </p>

        <ul class="card-single__facts">
          <li>
            <strong>{{ t('cards.fields.cost') }}</strong>
            <CostDice v-if="item.cost" :cost="item.cost" size="medium" />
            <span v-else>—</span>
          </li>
          <li v-if="item.hands">
            <strong>{{ t('cards.fields.hands') }}</strong>
            <span>{{ item.hands }}</span>
          </li>
          <li v-if="item.hero_ability">
            <strong>{{ t('cards.fields.heroAbility') }}</strong>
            <span>{{ tr(item.hero_ability.name) }}</span>
          </li>
        </ul>
        <p v-if="attackChips.length" class="single__meta">
          <span v-for="chip in attackChips" :key="chip" class="chip">{{ chip }}</span>
        </p>

        <template v-if="tr(item.effect) !== '—' || tr(item.restriction) !== '—'">
          <h2 class="single__section">{{ t('cards.sections.effects') }}</h2>
          <div class="rich-content" v-html="tr(item.effect)" />
          <template v-if="tr(item.restriction) !== '—'">
            <h3 class="card-single__restriction">{{ t('cards.fields.restriction') }}</h3>
            <div class="rich-content" v-html="tr(item.restriction)" />
          </template>
        </template>
      </div>
    </div>

    <template v-if="tr(item.lore_text) !== '—' || tr(item.epic_quote) !== '—'">
      <h2 class="single__section">{{ t('cards.sections.lore') }}</h2>
      <div class="rich-content" v-html="tr(item.lore_text)" />
      <blockquote
        v-if="tr(item.epic_quote) !== '—'"
        class="card-single__quote rich-content"
        v-html="tr(item.epic_quote)"
      />
    </template>

    <!-- PNG generados por locale, con regeneración en cola -->
    <PreviewPanel :id="item.id" ref="previewPanel" entity="card" />

    <CardFormModal v-model="formOpen" mode="edit" :target-slug="slug" @saved="onSaved" />
  </div>
  <p v-else-if="!loading" class="single__empty">{{ t('common.empty') }}</p>
</template>
