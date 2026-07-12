<script setup lang="ts">
import { onMounted } from 'vue'
import { ArrowRight, Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, FilterBar, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BaseTabs } from '@edc-motor/ui'
import { useEntityList } from '@/composables/useEntityList'
import type { Card } from '@juego/shared'
import CardFormModal from '@/components/cards/CardFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'
import CostDice from '@/components/game/CostDice.vue'

// Cartas: entidad completa con slug, single, publicación y previews PNG.
// TODO filtros extra del listado (facción/tipo/coste): pasarlos por
// extraParams cuando la vista los pinte; el controller ya los espera.
const {
  t,
  items,
  loading,
  status,
  search,
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

onMounted(init)
</script>

<template>
  <div class="cards-view">
    <div class="list-view__top">
      <BaseButton @click="openCreate">
        <template #icon><Plus :size="16" /></template>
        {{ t('cards.newButton') }}
      </BaseButton>
    </div>

    <!-- Filtros por encima de las tabs (estilo kontuan) -->
    <FilterBar v-model="search" :placeholder="t('common.search')" />
    <BaseTabs v-model="status" :tabs="tabs" />

    <EmptyState v-if="!loading && !items.length" :title="t('common.empty')" />

    <BaseGrid v-else preset="cards" gap="md">
      <EntityCard
        v-for="item in items"
        :key="item.id"
        :title="tr(item.name)"
        :muted="!!item.deleted_at"
        :active="selectedId === item.id"
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

        <template #badges>
          <span v-if="item.deleted_at" class="chip is-failed">{{ t('cards.state.trashed') }}</span>
          <span v-else-if="item.is_published" class="chip is-ok">{{
            t('cards.state.published')
          }}</span>
          <span v-else class="chip">{{ t('cards.state.draft') }}</span>
        </template>

        <template #meta>
          <span>{{ item.faction ? tr(item.faction.name) : t('cards.fields.noFaction') }}</span>
          <span v-if="item.card_type">· {{ tr(item.card_type.name) }}</span>
          <span v-if="item.cost">· <CostDice :cost="item.cost" /></span>
        </template>
      </EntityCard>
    </BaseGrid>

    <CardFormModal v-model="formOpen" :mode="formMode" :target-slug="formSlug" @saved="onSaved" />

    <EntityPanel
      :item="selected"
      :name="selected ? tr(selected.name) : ''"
      :kicker="t('cards.panelTitle')"
      :empty="t('cards.panelEmpty')"
      has-preview
      @open="selected && goSingle(selected)"
      @edit="selected && edit(selected)"
      @toggle-publish="selected && togglePublish(selected)"
      @regenerate="selected && regeneratePreview(selected)"
      @del="selected && del(selected)"
      @restore="selected && restore(selected)"
      @force-delete="selected && forceDelete(selected)"
    >
      <template #meta>
        <p v-if="selected" class="manager-detail__meta">
          <span>{{ selected.card_type ? tr(selected.card_type.name) : '—' }}</span>
          <span v-if="selected.card_subtype">· {{ tr(selected.card_subtype.name) }}</span>
        </p>
        <p v-if="selected" class="manager-detail__meta">
          <span>{{
            selected.faction ? tr(selected.faction.name) : t('cards.fields.noFaction')
          }}</span>
          <span v-if="selected.cost">· <CostDice :cost="selected.cost" /></span>
          <span v-if="selected.is_unique">· {{ t('cards.fields.isUnique') }}</span>
        </p>
      </template>
    </EntityPanel>
  </div>
</template>
