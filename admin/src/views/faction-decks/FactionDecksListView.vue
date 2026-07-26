<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { ArrowRight, Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BasePagination, BaseTabs, useToast } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { useEntityList } from '@/composables/useEntityList'
import type { DeckPublishError, FactionDeck, Translations } from '@juego/shared'
import FactionDeckFormModal from '@/components/faction-decks/FactionDeckFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'
import ListToolbar from '@/components/ListToolbar.vue'

// Listado de mazos de facción. La tarjeta selecciona (panel derecho con las
// acciones); "entrar" abre la single con el editor de cartas y héroes.
const {
  t,
  locales,
  items,
  meta,
  loading,
  page,
  pages,
  status,
  search,
  sort,
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
  // Enlaces entrantes por query (p. ej. desde el panel/single de facción).
  queryFilters: ['faction_id'],
})

const toast = useToast()

// Contenido del mazo seleccionado (héroes sin copias, cartas con copias)
// para el panel derecho: se carga bajo demanda con el show al seleccionar.
const selectedDetail = ref<FactionDeck | null>(null)
watch(selected, async (item) => {
  selectedDetail.value = null
  if (!item || item.deleted_at) return
  try {
    const { data } = await api.get(`/admin/faction-decks/${slugFor(item)}`)
    if (selected.value?.id === item.id) selectedDetail.value = data.data
  } catch {
    selectedDetail.value = null
  }
})

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

/** Slug localizado de un héroe/carta embebido (enlace a su single). */
function slugOf(item: { slug?: Translations }): string {
  return item.slug?.[locales.current] || Object.values(item.slug ?? {})[0] || ''
}

/** Tras guardar el modal: refresca y, si es un mazo RECIÉN CREADO, directo
 *  a su editor — ahí se añaden las cartas y los héroes (y se publica). */
function onDeckSaved(created?: FactionDeck) {
  onSaved()
  if (created) goSingle(created)
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

        <!-- Sin badge de estado (los tabs ya separan): el modo de juego y las
             FACCIONES como chips teñidos (is-tinted, como héroes y cartas) -->
        <template #badges>
          <span v-if="item.game_mode" class="chip">{{ tr(item.game_mode.name) }}</span>
          <span
            v-for="faction in item.factions ?? []"
            :key="faction.id"
            class="chip"
            :class="{ 'is-tinted': !!faction.color }"
            :style="faction.color ? { '--chip-tint': faction.color } : undefined"
            >{{ tr(faction.name) }}</span
          >
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

    <FactionDeckFormModal
      v-model="formOpen"
      :mode="formMode"
      :target-slug="formSlug"
      @saved="onDeckSaved"
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
        <!-- Contenido asignado, en secciones con divisoria + kicker (patrón
             de héroes/cartas/facciones), cada sección con su total entre
             paréntesis y cada elemento enlazado a su single -->
        <div v-if="selectedDetail" class="faction-decks__content">
          <template v-if="selectedDetail.heroes?.length">
            <hr class="manager-panel__divider" />
            <p class="manager-panel__kicker">
              {{ t('factionDecks.single.heroesTitle') }} ({{ selected?.total_heroes ?? 0 }})
            </p>
            <ul>
              <li v-for="hero in selectedDetail.heroes" :key="hero.id">
                <RouterLink
                  class="hero-link faction-decks__content-name"
                  :to="{ name: 'hero-single', params: { slug: slugOf(hero) } }"
                  >{{ tr(hero.name) }}</RouterLink
                >
              </li>
            </ul>
          </template>
          <template v-if="selectedDetail.cards?.length">
            <hr class="manager-panel__divider" />
            <p class="manager-panel__kicker">
              {{ t('factionDecks.single.cardsTitle') }} ({{ selected?.total_cards ?? 0 }})
            </p>
            <ul>
              <li v-for="card in selectedDetail.cards" :key="card.id">
                <RouterLink
                  class="hero-link faction-decks__content-name"
                  :to="{ name: 'card-single', params: { slug: slugOf(card) } }"
                  >{{ tr(card.name) }}</RouterLink
                >
                <span v-if="card.copies > 1" class="faction-decks__content-copies"
                  >×{{ card.copies }}</span
                >
              </li>
            </ul>
          </template>
        </div>
      </template>
    </EntityPanel>
  </div>
</template>
