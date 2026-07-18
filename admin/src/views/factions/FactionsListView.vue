<script setup lang="ts">
import { onMounted } from 'vue'
import { ArrowRight, Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BasePagination, BaseTabs } from '@edc-motor/ui'
import { useEntityList } from '@/composables/useEntityList'
import type { Faction } from '@juego/shared'
import FactionFormModal from '@/components/factions/FactionFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'
import ListToolbar from '@/components/ListToolbar.vue'

// La tarjeta selecciona (panel derecho con TODAS las acciones); en la
// tarjeta quedan solo las básicas: abrir y editar.
const {
  t,
  items,
  loading,
  page,
  pages,
  status,
  search,
  sort,
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
  selectedId,
  selected,
  select,
} = useEntityList<Faction>({
  resource: '/admin/factions',
  ns: 'factions',
  singleRoute: 'faction-single',
  nameOf: (item) => item.name,
})

onMounted(init)
</script>

<!-- eslint-disable vue/no-v-html -- HTML del WYSIWYG propio (sanitización en servidor) -->
<template>
  <div class="factions">
    <div class="list-view__top">
      <BaseButton @click="openCreate">
        <template #icon><Plus :size="16" /></template>
        {{ t('factions.newButton') }}
      </BaseButton>
    </div>

    <!-- Barra del índice: búsqueda + toggles de ordenación -->
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
        :accent-color="item.color || undefined"
        clickable
        @view="select(item)"
      >
        <template #media>
          <div class="faction-emblem" :style="{ '--c': item.color || 'transparent' }">
            <img v-if="item.image" :src="item.image" alt="" />
            <span v-else class="faction-emblem__mono">{{ tr(item.name).charAt(0) }}</span>
          </div>
        </template>

        <!-- La tarjeta solo lleva "entrar" al single; el resto, en el panel -->
        <template #actions>
          <button v-if="!item.deleted_at" type="button" class="card-enter" @click="goSingle(item)">
            {{ t('common.actions.enter') }} <ArrowRight :size="14" />
          </button>
        </template>

        <!-- Sin badge de estado (los tabs ya separan): solo la badge del
             color identitario, teñida con el propio color de la facción -->
        <template #badges>
          <span
            class="chip faction-color-chip"
            :class="{ 'is-dark-text': item.text_is_dark }"
            :style="{ '--c': item.color || 'transparent' }"
            >{{ item.color || '—' }}</span
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

    <FactionFormModal
      v-model="formOpen"
      :mode="formMode"
      :target-slug="formSlug"
      @saved="onSaved"
    />

    <EntityPanel
      :item="selected"
      :name="selected ? tr(selected.name) : ''"
      :kicker="t('factions.panelTitle')"
      :empty="t('factions.panelEmpty')"
      @open="selected && goSingle(selected)"
      @edit="selected && edit(selected)"
      @toggle-publish="selected && togglePublish(selected)"
      @del="selected && del(selected)"
      @restore="selected && restore(selected)"
      @force-delete="selected && forceDelete(selected)"
    >
      <!-- Lore + cantidades de héroes, cartas y mazos (texto plano) -->
      <template #meta>
        <template v-if="selected">
          <div
            v-if="tr(selected.lore_text) !== '—'"
            class="rich-content factions__panel-lore"
            v-html="tr(selected.lore_text)"
          ></div>
          <ul class="factions__panel-counts">
            <li>
              <strong>{{ t('factions.counts.heroes') }}</strong
              ><span>{{ selected.heroes_count ?? 0 }}</span>
            </li>
            <li>
              <strong>{{ t('factions.counts.cards') }}</strong
              ><span>{{ selected.cards_count ?? 0 }}</span>
            </li>
            <li>
              <strong>{{ t('factions.counts.decks') }}</strong
              ><span>{{ selected.faction_decks_count ?? 0 }}</span>
            </li>
          </ul>
        </template>
      </template>
    </EntityPanel>
  </div>
</template>
