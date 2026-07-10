<script setup lang="ts">
import { onMounted } from 'vue'
import { ArrowRight, Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, FilterBar, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BaseTabs, useToast } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { useEntityList } from '@/composables/useEntityList'
import type { DeckPublishError, FactionDeck } from '@juego/shared'
import FactionDeckFormModal from '@/components/faction-decks/FactionDeckFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'

// Listado de mazos de facción. La tarjeta selecciona (panel derecho con las
// acciones); "entrar" abre la single con el editor de cartas y héroes.
const {
  t,
  items,
  meta,
  loading,
  status,
  search,
  tabs,
  tr,
  slugFor,
  load,
  init,
  formOpen,
  formMode,
  formSlug,
  openCreate,
  edit,
  goSingle,
  onSaved,
  del,
  restore,
  forceDelete,
  selectedId,
  selected,
  select,
} = useEntityList<FactionDeck>({
  resource: '/admin/faction-decks',
  ns: 'factionDecks',
  singleRoute: 'faction-deck-single',
  nameOf: (item) => item.name,
})

const toast = useToast()

/**
 * Publicar/despublicar con los errores de límites del servidor: si el 422
 * trae `errors.deck`, se muestra el primero localizado (clave i18n + params).
 */
async function togglePublishDeck(item: FactionDeck) {
  try {
    await api.post(`/admin/faction-decks/${slugFor(item)}/toggle-published`)
    toast.success(
      item.is_published ? t('factionDecks.toast.unpublished') : t('factionDecks.toast.published'),
    )
    load(meta.value?.current_page ?? 1)
  } catch (e) {
    const deck = (e as { response?: { data?: { errors?: { deck?: DeckPublishError[] } } } })
      ?.response?.data?.errors?.deck
    if (Array.isArray(deck) && deck.length) {
      toast.danger(t(deck[0].key, deck[0].params ?? {}))
    } else {
      toast.danger(t('common.errors.action'))
    }
  }
}

onMounted(init)
</script>

<template>
  <div class="faction-decks">
    <div class="list-view__top">
      <BaseButton @click="openCreate">
        <template #icon><Plus :size="16" /></template>
        {{ t('factionDecks.newButton') }}
      </BaseButton>
    </div>

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
          <div class="faction-decks__emblem">
            <img v-if="item.image" :src="item.image" alt="" />
            <span v-else class="faction-decks__mono">{{ tr(item.name).charAt(0) }}</span>
          </div>
        </template>

        <!-- La tarjeta solo lleva "entrar" a la single; el resto, en el panel -->
        <template #actions>
          <button v-if="!item.deleted_at" type="button" class="card-enter" @click="goSingle(item)">
            {{ t('common.actions.enter') }} <ArrowRight :size="14" />
          </button>
        </template>

        <template #badges>
          <span v-if="item.deleted_at" class="chip is-failed">{{
            t('factionDecks.state.trashed')
          }}</span>
          <span v-else-if="item.is_published" class="chip is-ok">{{
            t('factionDecks.state.published')
          }}</span>
          <span v-else class="chip">{{ t('factionDecks.state.draft') }}</span>
          <span v-if="item.game_mode" class="chip">{{ tr(item.game_mode.name) }}</span>
        </template>

        <template #meta>
          <span>{{ t('factionDecks.counts.cards', { count: item.total_cards }) }}</span>
          <span>{{ t('factionDecks.counts.heroes', { count: item.total_heroes }) }}</span>
          <span
            v-for="faction in item.factions ?? []"
            :key="faction.id"
            class="faction-decks__faction-tag"
          >
            <span class="swatch" :style="{ background: faction.color || 'transparent' }" />
            {{ tr(faction.name) }}
          </span>
        </template>
      </EntityCard>
    </BaseGrid>

    <FactionDeckFormModal
      v-model="formOpen"
      :mode="formMode"
      :target-slug="formSlug"
      @saved="onSaved"
    />

    <EntityPanel
      :item="selected"
      :name="selected ? tr(selected.name) : ''"
      :kicker="t('factionDecks.panelTitle')"
      :empty="t('factionDecks.panelEmpty')"
      @open="selected && goSingle(selected)"
      @edit="selected && edit(selected)"
      @toggle-publish="selected && togglePublishDeck(selected)"
      @del="selected && del(selected)"
      @restore="selected && restore(selected)"
      @force-delete="selected && forceDelete(selected)"
    >
      <template #meta>
        <p v-if="selected" class="manager-detail__meta">
          {{ t('factionDecks.counts.cards', { count: selected.total_cards }) }} ·
          {{ t('factionDecks.counts.heroes', { count: selected.total_heroes }) }}
        </p>
      </template>
    </EntityPanel>
  </div>
</template>
