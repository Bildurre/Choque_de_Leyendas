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
import CardEffect from '@/components/cards/CardEffect.vue'
import PreviewPanel from '@/components/previews/PreviewPanel.vue'
import InfoBlock from '@/components/InfoBlock.vue'
import CostDice from '@/components/game/CostDice.vue'
import AttackLine from '@/components/game/AttackLine.vue'

// Single de carta en secciones info-block (borde sin fondo): detalles de la
// carta y, si corresponde, del ataque — texto plano o coloreado, sin chips
// (regla transversal). El efecto integra la habilidad de héroe otorgada,
// como en la preview (CardEffect).
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

/** La carta lleva línea de ataque (rango, tipo o subtipo). */
const hasAttack = computed(
  () =>
    !!item.value &&
    !!(item.value.attack_range || item.value.attack_type || item.value.attack_subtype),
)

/** Hay algo que pintar en la sección de efecto (incluida la habilidad). */
const hasEffectContent = computed(
  () =>
    !!item.value &&
    (tr(item.value.effect) !== '—' ||
      tr(item.value.restriction) !== '—' ||
      !!item.value.hero_ability),
)

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

        <!-- Sin chips: texto plano, la facción coloreada y la única en ámbar -->
        <InfoBlock :title="t('cards.sections.details')">
          <dl class="info-list">
            <dt>{{ t('cards.fields.faction') }}</dt>
            <dd :style="item.faction?.color ? { color: item.faction.color } : undefined">
              {{ item.faction ? tr(item.faction.name) : t('cards.fields.noFaction') }}
            </dd>

            <template v-if="item.cost">
              <dt>{{ t('cards.fields.cost') }}</dt>
              <dd><CostDice :cost="item.cost" size="medium" /></dd>
            </template>

            <template v-if="item.card_type">
              <dt>{{ t('cards.fields.type') }}</dt>
              <dd>{{ tr(item.card_type.name) }}</dd>
            </template>

            <template v-if="item.card_subtype">
              <dt>{{ t('cards.fields.subtype') }}</dt>
              <dd>{{ tr(item.card_subtype.name) }}</dd>
            </template>

            <template v-if="item.equipment_type">
              <dt>{{ t('cards.fields.equipmentType') }}</dt>
              <dd>{{ tr(item.equipment_type.name) }}</dd>
            </template>

            <template v-if="item.equipment_subtype">
              <dt>{{ t('cards.fields.equipmentSubtype') }}</dt>
              <dd>{{ tr(item.equipment_subtype.name) }}</dd>
            </template>

            <template v-if="item.hands">
              <dt>{{ t('cards.fields.hands') }}</dt>
              <dd>
                {{ t(item.hands > 1 ? 'cards.fields.twoHands' : 'cards.fields.oneHand') }}
              </dd>
            </template>

            <template v-if="item.is_unique">
              <dt>{{ t('cards.fields.isUnique') }}</dt>
              <dd>
                <span class="tinted-unique">{{ t('cards.state.unique') }}</span>
              </dd>
            </template>
          </dl>
        </InfoBlock>

        <!-- Detalles del ataque, si corresponde: rango-tipo-subtipo (+ área) -->
        <InfoBlock v-if="hasAttack" :title="t('cards.sections.attack')">
          <AttackLine
            :range="item.attack_range"
            :type="item.attack_type"
            :subtype="item.attack_subtype"
            :area="item.area"
          />
        </InfoBlock>
      </div>
    </div>

    <!-- Efecto con la habilidad de héroe integrada, como en la preview -->
    <template v-if="hasEffectContent">
      <h2 class="single__section">{{ t('cards.sections.effects') }}</h2>
      <CardEffect :card="item" />
    </template>

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
