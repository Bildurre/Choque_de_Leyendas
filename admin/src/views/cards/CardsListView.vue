<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ArrowRight, Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BasePagination, BaseSelect, BaseTabs } from '@edc-motor/ui'
import { useEntityList } from '@/composables/useEntityList'
import { api } from '@/lib/api'
import type { Card, Translations } from '@juego/shared'
import CardFormModal from '@/components/cards/CardFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'
import ListToolbar from '@/components/ListToolbar.vue'
import CostDice from '@/components/game/CostDice.vue'
import AttackLine from '@/components/game/AttackLine.vue'
import CardEffect from '@/components/cards/CardEffect.vue'

// Cartas: entidad completa con slug, single, publicación y previews PNG.
// El listado filtra por facción y tipo con selects en el panel derecho
// (slot `filters` del EntityPanel).
const {
  t,
  items,
  loading,
  page,
  pages,
  status,
  search,
  sort,
  filters,
  tabs,
  tr,
  init,
  formOpen,
  formMode,
  formSlug,
  openCreate,
  edit,
  goSingle,
  onSaved,
  togglePublish,
  del,
  restore,
  forceDelete,
  regeneratePreview,
  selectedId,
  selected,
  select,
} = useEntityList<Card>({
  resource: '/admin/cards',
  ns: 'cards',
  singleRoute: 'card-single',
  nameOf: (item) => item.name,
  previewKey: 'card',
})

// Opciones de los selects de filtro del panel (endpoints options,
// nombres traducibles).
interface FilterOption {
  id: number
  name: Translations
}
const factions = ref<FilterOption[]>([])
const cardTypes = ref<FilterOption[]>([])

const factionOptions = computed(() => [
  { value: '', label: t('cards.filters.allFactions') },
  ...factions.value.map((f) => ({ value: String(f.id), label: tr(f.name) })),
])

/**
 * Línea de tipado de una carta: tipo · subtipo · tipo de equipo · subtipo
 * de equipo · manos (como el render de la carta). El coste va DELANTE.
 */
function typeLine(item: Card): string {
  const parts = [
    item.card_type ? tr(item.card_type.name) : null,
    item.card_subtype ? tr(item.card_subtype.name) : null,
    item.equipment_type ? tr(item.equipment_type.name) : null,
    item.equipment_subtype ? tr(item.equipment_subtype.name) : null,
    item.hands ? t(item.hands > 1 ? 'cards.fields.twoHands' : 'cards.fields.oneHand') : null,
  ]
  return parts.filter(Boolean).join(' · ')
}
/** La carta tiene algo que pintar en la sección de efecto del panel. */
function hasEffectContent(item: Card): boolean {
  return tr(item.effect) !== '—' || tr(item.restriction) !== '—' || !!item.hero_ability
}

const cardTypeOptions = computed(() => [
  { value: '', label: t('cards.filters.allTypes') },
  ...cardTypes.value.map((ct) => ({ value: String(ct.id), label: tr(ct.name) })),
])

async function loadFilterOptions() {
  try {
    const [factionsRes, typesRes] = await Promise.all([
      api.get('/admin/factions/options'),
      api.get('/admin/card-types/options'),
    ])
    factions.value = factionsRes.data.data
    cardTypes.value = typesRes.data.data
  } catch {
    // Sin opciones no hay filtro, pero el listado sigue funcionando.
  }
}

onMounted(async () => {
  await Promise.all([init(), loadFilterOptions()])
})
</script>

<template>
  <div class="cards-view">
    <div class="list-view__top">
      <BaseButton @click="openCreate">
        <template #icon><Plus :size="16" /></template>
        {{ t('cards.newButton') }}
      </BaseButton>
    </div>

    <!-- Barra del índice: búsqueda + orden (los filtros, en el panel derecho) -->
    <ListToolbar v-model="search" v-model:sort="sort" />
    <BaseTabs v-model="status" :tabs="tabs" />
    <BasePagination
      v-model:page="page"
      :pages="pages"
      class="list-view__pagination"
      :prev-label="t('common.pagination.prev')"
      :next-label="t('common.pagination.next')"
      :of-label="t('common.pagination.of', { page, pages })"
    />

    <EmptyState v-if="!loading && !items.length" :title="t('common.empty')" />

    <BaseGrid v-else preset="cards" gap="md">
      <EntityCard
        v-for="item in items"
        :key="item.id"
        :title="tr(item.name)"
        :muted="!!item.deleted_at"
        :active="selectedId === item.id"
        :accent-color="item.faction?.color || undefined"
        clickable
        @view="select(item)"
      >
        <template #media>
          <div class="card-art">
            <img v-if="item.image" :src="item.image" alt="" />
            <span v-else class="card-art__cost">{{ tr(item.name).charAt(0) }}</span>
          </div>
        </template>

        <!-- La tarjeta solo lleva "entrar" al single; el resto, en el panel -->
        <template #actions>
          <button v-if="!item.deleted_at" type="button" class="card-enter" @click="goSingle(item)">
            {{ t('common.actions.enter') }} <ArrowRight :size="14" />
          </button>
        </template>

        <!-- Sin badge de estado (los tabs ya separan): el tipado completo en
             badges — facción (teñida con su color), tipo, subtipo, equipo,
             manos y única (ámbar) -->
        <template #badges>
          <span
            class="chip"
            :style="item.faction?.color ? { color: item.faction.color } : undefined"
            >{{ item.faction ? tr(item.faction.name) : t('cards.fields.noFaction') }}</span
          >
          <span v-if="item.card_type" class="chip">{{ tr(item.card_type.name) }}</span>
          <span v-if="item.card_subtype" class="chip">{{ tr(item.card_subtype.name) }}</span>
          <span v-if="item.equipment_type" class="chip">{{ tr(item.equipment_type.name) }}</span>
          <span v-if="item.equipment_subtype" class="chip">{{
            tr(item.equipment_subtype.name)
          }}</span>
          <span v-if="item.hands" class="chip">{{
            t(item.hands > 1 ? 'cards.fields.twoHands' : 'cards.fields.oneHand')
          }}</span>
          <span v-if="item.is_unique" class="chip is-unique">{{ t('cards.state.unique') }}</span>
        </template>

        <!-- Coste + línea de ataque + marca de habilidad de héroe otorgada -->
        <template #meta>
          <span v-if="item.cost"><CostDice :cost="item.cost" /></span>
          <AttackLine
            v-if="item.attack_range || item.attack_type || item.attack_subtype"
            :range="item.attack_range"
            :type="item.attack_type"
            :subtype="item.attack_subtype"
            :area="item.area"
          />
          <span v-if="item.hero_ability_id">{{ t('cards.fields.withHeroAbility') }}</span>
        </template>
      </EntityCard>
    </BaseGrid>

    <BasePagination
      v-model:page="page"
      :pages="pages"
      class="list-view__pagination list-view__pagination--bottom"
      :prev-label="t('common.pagination.prev')"
      :next-label="t('common.pagination.next')"
      :of-label="t('common.pagination.of', { page, pages })"
    />

    <CardFormModal v-model="formOpen" :mode="formMode" :target-slug="formSlug" @saved="onSaved" />

    <EntityPanel
      :item="selected"
      :name="selected ? tr(selected.name) : ''"
      :kicker="t('cards.panelTitle')"
      :empty="t('cards.panelEmpty')"
      has-preview
      @deselect="selectedId = null"
      @open="selected && goSingle(selected)"
      @edit="selected && edit(selected)"
      @toggle-publish="selected && togglePublish(selected)"
      @regenerate="selected && regeneratePreview(selected)"
      @del="selected && del(selected)"
      @restore="selected && restore(selected)"
      @force-delete="selected && forceDelete(selected)"
    >
      <!-- Filtros del listado: aplican en vivo (sin guardar) -->
      <template #filters>
        <BaseSelect
          v-model="filters.faction_id"
          :label="t('cards.fields.faction')"
          :options="factionOptions"
        />
        <BaseSelect
          v-model="filters.card_type_id"
          :label="t('cards.fields.type')"
          :options="cardTypeOptions"
        />
      </template>

      <template #meta>
        <!-- Coste DELANTE del tipado; única DENTRO del tipado como texto
             coloreado (sin chips en el panel, regla transversal) -->
        <p v-if="selected" class="manager-detail__meta card-panel-meta">
          <span v-if="selected.cost"><CostDice :cost="selected.cost" /></span>
          <span>{{ typeLine(selected) || '—' }}</span>
          <span v-if="selected.is_unique" class="tinted-unique">{{ t('cards.state.unique') }}</span>
        </p>
        <!-- Línea de ataque, si corresponde: rango-tipo-subtipo (+ área) -->
        <p
          v-if="
            selected && (selected.attack_range || selected.attack_type || selected.attack_subtype)
          "
          class="manager-detail__meta"
        >
          <AttackLine
            :range="selected.attack_range"
            :type="selected.attack_type"
            :subtype="selected.attack_subtype"
            :area="selected.area"
          />
        </p>
        <p v-if="selected" class="manager-detail__meta">
          <span>{{
            selected.faction ? tr(selected.faction.name) : t('cards.fields.noFaction')
          }}</span>
        </p>

        <!-- Efecto (con la habilidad de héroe integrada), sobre las previews -->
        <template v-if="selected && hasEffectContent(selected)">
          <h4 class="cards__panel-title">{{ t('cards.sections.effects') }}</h4>
          <CardEffect :card="selected" />
        </template>
      </template>
    </EntityPanel>
  </div>
</template>
